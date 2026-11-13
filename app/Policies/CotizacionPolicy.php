<?php

namespace App\Policies;

use App\Dominio\Aprobaciones\ReglasDeAprobacion;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\Cotizacion;
use App\Models\User;

/**
 * Permisos sobre una cotizacion.
 *
 * Se apoya en las reglas del flujo de aprobacion para editar y borrar: lo
 * que esta aprobado no se toca, y mientras espera una decision solo la
 * toca quien debe tomarla. Esa regla ya existe y esta probada; repetirla
 * aqui seria arriesgarse a que las dos se separen.
 */
class CotizacionPolicy
{
    private function permisos(): Permisos
    {
        return new Permisos();
    }

    private function reglas(): ReglasDeAprobacion
    {
        return new ReglasDeAprobacion();
    }

    public function viewAny(User $usuario): bool
    {
        return $this->permisos()->puede($usuario, Capacidad::VerCotizaciones);
    }

    public function view(User $usuario, Cotizacion $cotizacion): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->permisos()->puede($usuario, Capacidad::CrearCotizaciones);
    }

    /** Lo decide el flujo de aprobacion, no el rol por si solo. */
    public function update(User $usuario, Cotizacion $cotizacion): bool
    {
        return $this->reglas()->puedeEditar($cotizacion, $usuario);
    }

    /**
     * Borrar arrastra items, recursos y asignaciones.
     *
     * Hacen falta las dos cosas: el permiso para borrar y que el flujo
     * permita tocarla. Una cotizacion aprobada no se borra aunque quien lo
     * pida sea administrador.
     */
    public function delete(User $usuario, Cotizacion $cotizacion): bool
    {
        return $this->permisos()->puede($usuario, Capacidad::BorrarCotizaciones)
            && $this->reglas()->puedeEditar($cotizacion, $usuario);
    }

    public function deleteAny(User $usuario): bool
    {
        return $this->permisos()->puede($usuario, Capacidad::BorrarCotizaciones);
    }

    public function restore(User $usuario, Cotizacion $cotizacion): bool
    {
        return $this->delete($usuario, $cotizacion);
    }

    public function forceDelete(User $usuario, Cotizacion $cotizacion): bool
    {
        return $this->delete($usuario, $cotizacion);
    }
}
