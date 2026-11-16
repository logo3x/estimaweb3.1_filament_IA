<?php

namespace App\Filament\Resources\CajasEpps\Pages;

use App\Filament\Resources\CajasEpps\CajaEppResource;
use App\Filament\Resources\Pages\GestionarCajas;

/**
 * Listado y edicion de mis cajas de EPP, todo en la misma pantalla.
 */
class GestionarCajaEpp extends GestionarCajas
{
    protected static string $resource = CajaEppResource::class;

    /** Sin esto Filament capitaliza cada palabra: "Cajas De Herramientas". */
    public function getTitle(): string
    {
        return 'Cajas de EPP';
    }
}
