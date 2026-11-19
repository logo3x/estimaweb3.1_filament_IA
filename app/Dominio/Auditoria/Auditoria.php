<?php

namespace App\Dominio\Auditoria;

use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Deja constancia de quien cambio que.
 *
 * Dos reglas que gobiernan todo lo de aqui:
 *
 * 1. **La auditoria nunca tumba la operacion.** Si no se puede escribir la
 *    fila, se anota en el log y se sigue: perder el trabajo del usuario
 *    por no poder auditarlo seria peor que no auditarlo.
 *
 * 2. **Solo se registra lo que importa.** Auditar cada campo de cada tabla
 *    llena la bitacora de ruido y la vuelve inservible justo cuando hace
 *    falta. Se registran los campos que mueven el dinero o los permisos.
 */
class Auditoria
{
    /**
     * Campos que NUNCA se guardan, ni siquiera cifrados.
     *
     * @var list<string>
     */
    private const NUNCA = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
    ];

    /**
     * Campos que no aportan nada al auditarlos.
     *
     * @var list<string>
     */
    private const RUIDO = [
        'updated_at',
        'created_at',
        'id',
    ];

    /** Anota la creacion de algo. */
    public function creado(Model $modelo, ?string $resumen = null): ?RegistroDeCambio
    {
        return $this->anotar(
            modelo: $modelo,
            accion: AccionAuditada::Creado,
            cambios: null,
            resumen: $resumen,
        );
    }

    /**
     * Anota un cambio, con lo que habia antes y lo que hay ahora.
     *
     * Si no cambio nada relevante no escribe: una fila que dice "se guardo
     * sin cambios" solo estorba.
     */
    public function modificado(Model $modelo, ?string $resumen = null): ?RegistroDeCambio
    {
        $cambios = $this->cambiosDe($modelo);

        if ($cambios === []) {
            return null;
        }

        return $this->anotar(
            modelo: $modelo,
            accion: AccionAuditada::Modificado,
            cambios: $cambios,
            resumen: $resumen,
        );
    }

    public function borrado(Model $modelo, ?string $resumen = null): ?RegistroDeCambio
    {
        return $this->anotar(
            modelo: $modelo,
            accion: AccionAuditada::Borrado,
            cambios: null,
            resumen: $resumen,
        );
    }

    /**
     * Anota algo que no es un cambio de datos.
     *
     * Aprobar, rechazar, suplantar a otro usuario, restaurar un respaldo:
     * hechos que importan aunque no se vean como un UPDATE.
     */
    public function hecho(
        AccionAuditada $accion,
        Model $modelo,
        string $resumen,
        ?array $detalle = null,
        ?User $quien = null,
    ): ?RegistroDeCambio {
        return $this->anotar(
            modelo: $modelo,
            accion: $accion,
            cambios: $detalle,
            resumen: $resumen,
            quien: $quien,
        );
    }

    /**
     * Lo que cambio de verdad, sin ruido ni secretos.
     *
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    private function cambiosDe(Model $modelo): array
    {
        $cambios = [];

        foreach ($modelo->getDirty() as $campo => $nuevo) {
            if (in_array($campo, self::NUNCA, true) || in_array($campo, self::RUIDO, true)) {
                continue;
            }

            $viejo = $modelo->getOriginal($campo);

            /**
             * Se comparan como texto.
             *
             * La base devuelve "12.00" donde el formulario manda 12, y sin
             * esto cada guardado pareceria un cambio.
             */
            if ((string) $viejo === (string) $nuevo) {
                continue;
            }

            $cambios[$campo] = [
                'antes' => $this->presentable($viejo),
                'despues' => $this->presentable($nuevo),
            ];
        }

        return $cambios;
    }

    /** Un valor que se pueda guardar en JSON y leer despues. */
    private function presentable(mixed $valor): mixed
    {
        if ($valor instanceof \BackedEnum) {
            return $valor->value;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (is_scalar($valor) || $valor === null) {
            return $valor;
        }

        return mb_substr((string) json_encode($valor), 0, 500);
    }

    /** Escribe la fila. Nunca lanza. */
    private function anotar(
        Model $modelo,
        AccionAuditada $accion,
        ?array $cambios,
        ?string $resumen,
        ?User $quien = null,
    ): ?RegistroDeCambio {
        try {
            return RegistroDeCambio::create([
                'entidad' => class_basename($modelo),
                'entidad_id' => $modelo->getKey(),
                'accion' => $accion->value,
                'cotizacion_id' => $this->cotizacionDe($modelo),
                'cambios' => $cambios,
                'resumen' => $resumen === null ? null : mb_substr($resumen, 0, 500),
                'usuario_id' => ($quien ?? auth()->user())?->id,
                'ip' => request()->ip(),
                'cuando' => now(),
            ]);
        } catch (\Throwable $e) {
            /** La auditoria no puede costarle el trabajo al usuario. */
            Log::error('No se pudo auditar.', [
                'entidad' => class_basename($modelo),
                'id' => $modelo->getKey(),
                'accion' => $accion->value,
                'motivo' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * A que cotizacion pertenece lo que cambio.
     *
     * Permite responder "que le paso a ESTA cotizacion" de un vistazo, que
     * es la pregunta que se hace de verdad.
     */
    private function cotizacionDe(Model $modelo): ?int
    {
        if (class_basename($modelo) === 'Cotizacion') {
            return $modelo->getKey();
        }

        foreach (['cotizacion_id', 'id_cotizacion'] as $campo) {
            if (isset($modelo->{$campo})) {
                return (int) $modelo->{$campo};
            }
        }

        return null;
    }
}
