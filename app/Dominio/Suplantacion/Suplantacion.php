<?php

namespace App\Dominio\Suplantacion;

use App\Dominio\Auditoria\AccionAuditada;
use App\Dominio\Auditoria\Auditoria;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Entrar al panel como otra persona, para ver lo que ella ve.
 *
 * Resuelve un "a mi no me aparece el boton" sin pedirle la contraseña a
 * nadie, que es la alternativa habitual y es peor: una contraseña
 * compartida deja de identificar a quien actua.
 *
 * Tres cosas sostienen que esto sea seguro:
 *
 * 1. **Quien suplanta queda guardado en la sesion**, no en la base: al
 *    cerrar el navegador la suplantacion muere sola.
 * 2. **Se audita al entrar y al salir.** Durante ese rato se actua con los
 *    permisos del otro, de modo que sin el registro lo que se haga
 *    parecera hecho por el suplantado.
 * 3. **No se encadena.** Estando dentro de otro no se puede saltar a un
 *    tercero: el permiso se comprueba sobre quien suplanta de verdad.
 */
class Suplantacion
{
    /** Donde se guarda el id de quien suplanta. */
    private const CLAVE = 'suplantacion.autor';

    public function __construct(
        private readonly Auditoria $auditoria = new Auditoria(),
        private readonly Permisos $permisos = new Permisos(),
    ) {}

    /**
     * Entra como otra persona.
     *
     * @throws DomainException si no esta permitido
     */
    public function entrarComo(User $destino): void
    {
        $autor = Auth::user();

        $this->exigirQuePueda($autor, $destino);

        /**
         * Se guarda ANTES de cambiar de sesion.
         *
         * `Auth::login()` regenera la sesion, asi que escribir despues
         * perderia el dato y dejaria a quien suplanta atrapado dentro del
         * otro usuario, sin forma de volver.
         */
        Session::put(self::CLAVE, $autor->id);

        $this->auditoria->hecho(
            accion: AccionAuditada::Suplantacion,
            modelo: $destino,
            resumen: sprintf(
                '%s entró al panel como %s (%s).',
                $autor->name,
                $destino->name,
                $destino->rolPrincipal() ?? 'sin rol',
            ),
            detalle: [
                'autor_id' => $autor->id,
                'destino_id' => $destino->id,
                'inicio' => now()->toDateTimeString(),
            ],
            quien: $autor,
        );

        Auth::login($destino);

        /** La sesion se regenera al entrar: hay que reponer la marca. */
        Session::put(self::CLAVE, $autor->id);
    }

    /**
     * Vuelve a ser quien era.
     *
     * @throws DomainException si no habia ninguna suplantacion en curso
     */
    public function volver(): User
    {
        $autor = $this->quienSuplanta();

        if ($autor === null) {
            throw new DomainException('No hay ninguna suplantación en curso.');
        }

        $suplantado = Auth::user();

        $this->auditoria->hecho(
            accion: AccionAuditada::Suplantacion,
            modelo: $autor,
            resumen: sprintf(
                '%s dejó de actuar como %s.',
                $autor->name,
                $suplantado?->name ?? 'otro usuario',
            ),
            detalle: [
                'autor_id' => $autor->id,
                'destino_id' => $suplantado?->id,
                'fin' => now()->toDateTimeString(),
            ],
            quien: $autor,
        );

        Session::forget(self::CLAVE);

        Auth::login($autor);

        Session::forget(self::CLAVE);

        return $autor;
    }

    /** Si ahora mismo se esta actuando como otro. */
    public function estaEnCurso(): bool
    {
        return Session::has(self::CLAVE);
    }

    /** Quien suplanta de verdad, o null si nadie. */
    public function quienSuplanta(): ?User
    {
        $id = Session::get(self::CLAVE);

        return $id === null ? null : User::find($id);
    }

    /**
     * Si una persona puede ser suplantada por quien mira.
     *
     * Se usa para decidir si se ofrece el boton; la comprobacion de verdad
     * la hace `exigirQuePueda()` al ejecutar.
     */
    public function puedeEntrarComo(?User $destino): bool
    {
        try {
            $this->exigirQuePueda(Auth::user(), $destino);

            return true;
        } catch (DomainException) {
            return false;
        }
    }

    /** @throws DomainException */
    private function exigirQuePueda(?User $autor, ?User $destino): void
    {
        if ($autor === null || $destino === null) {
            throw new DomainException('No se pudo identificar al usuario.');
        }

        /**
         * Estando ya dentro de otro, no se salta a un tercero.
         *
         * Encadenar suplantaciones vuelve imposible reconstruir quien hizo
         * que, que es justamente lo que esto debe conservar.
         */
        if ($this->estaEnCurso()) {
            throw new DomainException('Ya está actuando como otro usuario. Vuelva a su cuenta primero.');
        }

        if (! $this->permisos->puede($autor, Capacidad::SuplantarUsuarios)) {
            throw new DomainException(Capacidad::SuplantarUsuarios->motivoDelBloqueo());
        }

        if ($autor->is($destino)) {
            throw new DomainException('Ya está en su propia cuenta.');
        }

        /**
         * Solo a quien de verdad puede entrar al panel.
         *
         * Suplantar a alguien que no entra no enseña nada y dejaria la
         * sesion en una pantalla de 403.
         */
        if (! $destino->hasAnyRole(User::ROLES_CON_ACCESO_AL_PANEL)) {
            throw new DomainException('Esa persona no tiene acceso al panel.');
        }
    }
}
