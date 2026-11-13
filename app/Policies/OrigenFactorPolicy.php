<?php

namespace App\Policies;

use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\User;

/**
 * Permisos sobre los factores legales.
 *
 * Es una sola fila -prestaciones, parafiscales, auxilios- que entra en el
 * costo de cada persona de cada cotizacion. Cambiarla mueve el precio de
 * todas a la vez, asi que queda en manos de la administracion.
 */
class OrigenFactorPolicy
{
    private function permisos(): Permisos
    {
        return new Permisos();
    }

    private function esAdministracion(User $usuario): bool
    {
        return $this->permisos()->puede($usuario, Capacidad::AdministrarFactoresLegales);
    }

    public function viewAny(User $usuario): bool
    {
        return $this->esAdministracion($usuario);
    }

    public function view(User $usuario): bool
    {
        return $this->esAdministracion($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->esAdministracion($usuario);
    }

    public function update(User $usuario): bool
    {
        return $this->esAdministracion($usuario);
    }

    /** No hay por que borrar la unica fila de factores. */
    public function delete(User $usuario): bool
    {
        return false;
    }
}
