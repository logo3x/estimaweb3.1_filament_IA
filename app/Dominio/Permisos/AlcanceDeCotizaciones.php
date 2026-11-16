<?php

namespace App\Dominio\Permisos;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Que cotizaciones ve cada quien.
 *
 * Cada usuario trabaja con las de **su gerencia**. La administracion las ve
 * todas, porque aprueba el segundo nivel y necesita comparar entre zonas.
 *
 * Hasta aqui el panel mostraba las cuarenta y ocho a cualquiera que
 * entrara: un creador de una zona veia -y podia abrir- los precios y
 * margenes de todas las demas.
 *
 * La regla vive aqui y no en la tabla porque hay que aplicarla en varios
 * sitios -el listado, el detalle, la busqueda global, el contador del
 * menu- y basta con que uno se olvide para que el filtro no sirva de nada.
 */
final class AlcanceDeCotizaciones
{
    /** Roles que ven las cotizaciones de todas las gerencias. */
    public const ROLES_SIN_LIMITE = [
        'Administrador',
        'super_admin',
    ];

    /**
     * Limita la consulta a lo que ese usuario puede ver.
     *
     * Sin usuario no devuelve nada: una consulta sin filtrar seria peor
     * que un error, porque pasaria desapercibida.
     */
    public function aplicar(Builder $query, ?User $usuario): Builder
    {
        if (! $usuario) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->veTodas($usuario)) {
            return $query;
        }

        /**
         * Un usuario sin gerencia solo ve lo suyo.
         *
         * Comparar su gerencia nula contra las cotizaciones sin gerencia
         * le daria acceso a las tres que estan asi en los datos reales,
         * que no son de nadie en particular.
         */
        if (blank($usuario->id_gerencia)) {
            return $query->where('id_user', $usuario->id);
        }

        return $query->where(function (Builder $query) use ($usuario): void {
            $query
                ->where('gerencia', (string) $usuario->id_gerencia)
                /** Lo propio se ve siempre, aunque se grabara sin gerencia. */
                ->orWhere('id_user', $usuario->id);
        });
    }

    /** Si ese usuario no tiene limite de gerencia. */
    public function veTodas(?User $usuario): bool
    {
        return (bool) $usuario?->hasAnyRole(self::ROLES_SIN_LIMITE);
    }

    /**
     * Si puede abrir una cotizacion concreta.
     *
     * Se comprueba aparte del listado: ocultar una fila no impide escribir
     * su direccion en el navegador.
     */
    public function puedeVer(\App\Models\Cotizacion $cotizacion, ?User $usuario): bool
    {
        if (! $usuario) {
            return false;
        }

        if ($this->veTodas($usuario)) {
            return true;
        }

        if ((int) $cotizacion->id_user === (int) $usuario->id) {
            return true;
        }

        return filled($usuario->id_gerencia)
            && (string) $cotizacion->gerencia === (string) $usuario->id_gerencia;
    }

    /** Por que no la puede ver, para explicarlo en pantalla. */
    public function motivoDelBloqueo(): string
    {
        return 'Esta cotización es de otra gerencia. Solo puede consultar las de la suya.';
    }
}
