<?php

namespace App\Dominio\Permisos;

use App\Models\User;

/**
 * Responde si un usuario puede ejercer una capacidad.
 *
 * Los roles SUMAN. Seis personas del sistema tienen varios a la vez -una
 * llega a tener cuatro-, asi que basta con que uno de ellos lo permita.
 * Preguntar solo por el primero dejaria fuera a quien si deberia poder.
 */
final class Permisos
{
    public const ROL_TOTAL = 'super_admin';

    public function puede(?User $usuario, Capacidad $capacidad): bool
    {
        if (! $usuario) {
            return false;
        }

        /** Resuelto aparte para no repetirlo en cada linea del enum. */
        if ($usuario->hasRole(self::ROL_TOTAL)) {
            return true;
        }

        return $usuario->hasAnyRole($capacidad->roles());
    }

    /** El usuario de la sesion. */
    public function puedeElActual(Capacidad $capacidad): bool
    {
        return $this->puede(auth()->user(), $capacidad);
    }

    /**
     * Las capacidades de un usuario, para revisarlas de un vistazo.
     *
     * La usa el comando de diagnostico: sin esto, comprobar quien puede que
     * obliga a entrar al panel con cada rol.
     *
     * @return list<Capacidad>
     */
    public function capacidadesDe(?User $usuario): array
    {
        return array_values(array_filter(
            Capacidad::cases(),
            fn (Capacidad $c): bool => $this->puede($usuario, $c),
        ));
    }
}
