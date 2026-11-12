<?php

namespace App\Filament\Resources\DatosEquipos\Pages;

use App\Filament\Resources\DatosEquipos\DatosEquipoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Equipos: listado y edicion en la misma pantalla.
 *
 * Un catalogo es una lista plana; abrir una pagina aparte para cambiar un
 * precio seria un paso de mas. Se edita en una ventana sobre el listado.
 */
class GestionarDatosEquipo extends ManageRecords
{
    protected static string $resource = DatosEquipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo'),
        ];
    }
}
