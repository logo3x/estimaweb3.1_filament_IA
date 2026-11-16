<?php

namespace App\Filament\Resources\CajasConsumibles\Pages;

use App\Filament\Resources\CajasConsumibles\CajaConsumibleResource;
use App\Filament\Resources\Pages\GestionarCajas;

/**
 * Listado y edicion de mis cajas de consumibles, todo en la misma pantalla.
 */
class GestionarCajaConsumible extends GestionarCajas
{
    protected static string $resource = CajaConsumibleResource::class;

    /** Sin esto Filament capitaliza cada palabra: "Cajas De Herramientas". */
    public function getTitle(): string
    {
        return 'Cajas de consumibles';
    }
}
