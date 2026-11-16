<?php

namespace App\Filament\Resources\CajasEquipos\Pages;

use App\Filament\Resources\CajasEquipos\CajaEquipoResource;
use App\Filament\Resources\Pages\GestionarCajas;

/**
 * Listado y edicion de mis cajas de equipos, todo en la misma pantalla.
 */
class GestionarCajaEquipo extends GestionarCajas
{
    protected static string $resource = CajaEquipoResource::class;

    /** Sin esto Filament capitaliza cada palabra: "Cajas De Herramientas". */
    public function getTitle(): string
    {
        return 'Cajas de equipos';
    }
}
