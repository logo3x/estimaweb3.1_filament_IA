<?php

namespace App\Policies;

use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\User;

/**
 * Permisos sobre usuarios y sus roles.
 *
 * Reservado a la administracion. Hasta aqui los diecisiete usuarios con rol
 * Creador podian entrar aqui y borrar a cualquiera, incluidos los
 * aprobadores.
 */
class UserPolicy
{
    private function permisos(): Permisos
    {
        return new Permisos();
    }

    private function esAdministracion(User $usuario): bool
    {
        return $this->permisos()->puede($usuario, Capacidad::AdministrarUsuarios);
    }

    public function viewAny(User $usuario): bool
    {
        return $this->esAdministracion($usuario);
    }

    public function view(User $usuario, User $objetivo): bool
    {
        /** Cualquiera puede ver su propia ficha. */
        return $this->esAdministracion($usuario) || $usuario->is($objetivo);
    }

    public function create(User $usuario): bool
    {
        return $this->esAdministracion($usuario);
    }

    public function update(User $usuario, User $objetivo): bool
    {
        return $this->esAdministracion($usuario) || $usuario->is($objetivo);
    }

    /**
     * Nadie se borra a si mismo.
     *
     * Un administrador que se borra por error pierde el acceso y deja el
     * sistema sin quien lo recupere, porque solo la administracion gestiona
     * usuarios.
     */
    public function delete(User $usuario, User $objetivo): bool
    {
        if ($usuario->is($objetivo)) {
            return false;
        }

        /** A super_admin solo lo toca otro super_admin. */
        if ($objetivo->hasRole(Permisos::ROL_TOTAL) && ! $usuario->hasRole(Permisos::ROL_TOTAL)) {
            return false;
        }

        return $this->esAdministracion($usuario);
    }

    public function deleteAny(User $usuario): bool
    {
        return $this->esAdministracion($usuario);
    }

    public function restore(User $usuario): bool
    {
        return $this->esAdministracion($usuario);
    }

    public function forceDelete(User $usuario, User $objetivo): bool
    {
        return $this->delete($usuario, $objetivo);
    }
}
