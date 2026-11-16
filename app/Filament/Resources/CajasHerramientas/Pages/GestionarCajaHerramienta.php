<?php

namespace App\Filament\Resources\CajasHerramientas\Pages;

use App\Filament\Resources\CajasHerramientas\CajaHerramientaResource;
use App\Filament\Resources\Pages\GestionarCajas;

/**
 * Listado y edicion de mis cajas de herramientas, todo en la misma pantalla.
 */
class GestionarCajaHerramienta extends GestionarCajas
{
    protected static string $resource = CajaHerramientaResource::class;

    /** Sin esto Filament capitaliza cada palabra: "Cajas De Herramientas". */
    public function getTitle(): string
    {
        return 'Cajas de herramientas';
    }
}
