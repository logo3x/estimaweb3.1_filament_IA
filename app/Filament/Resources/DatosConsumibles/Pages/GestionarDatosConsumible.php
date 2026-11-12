<?php

namespace App\Filament\Resources\DatosConsumibles\Pages;

use App\Filament\Resources\DatosConsumibles\DatosConsumibleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Consumibles: listado y edicion en la misma pantalla.
 *
 * Un catalogo es una lista plana; abrir una pagina aparte para cambiar un
 * precio seria un paso de mas. Se edita en una ventana sobre el listado.
 */
class GestionarDatosConsumible extends ManageRecords
{
    protected static string $resource = DatosConsumibleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo'),
        ];
    }
}
