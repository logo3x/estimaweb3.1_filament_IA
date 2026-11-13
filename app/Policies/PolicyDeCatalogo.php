<?php

namespace App\Policies;

use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\User;

/**
 * Permisos de los catalogos de precios.
 *
 * Son ocho catalogos -herramientas, equipos, consumibles, EPP,
 * comunicaciones, turnos, examenes y gerencias- con exactamente la misma
 * regla: los consulta cualquiera que cotice, los modifica la
 * administracion o un gerente.
 *
 * Una sola clase base en vez de ocho identicas: si la regla cambia, cambia
 * en un sitio. Cada catalogo la extiende solo para que Laravel la
 * encuentre por convencion de nombre.
 */
abstract class PolicyDeCatalogo
{
    protected function permisos(): Permisos
    {
        return new Permisos();
    }

    public function viewAny(User $usuario): bool
    {
        return $this->permisos()->puede($usuario, Capacidad::VerCatalogos);
    }

    public function view(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->permisos()->puede($usuario, Capacidad::EditarCatalogos);
    }

    public function update(User $usuario): bool
    {
        return $this->create($usuario);
    }

    public function delete(User $usuario): bool
    {
        return $this->create($usuario);
    }

    public function deleteAny(User $usuario): bool
    {
        return $this->create($usuario);
    }

    public function restore(User $usuario): bool
    {
        return $this->create($usuario);
    }

    public function forceDelete(User $usuario): bool
    {
        return $this->create($usuario);
    }
}
