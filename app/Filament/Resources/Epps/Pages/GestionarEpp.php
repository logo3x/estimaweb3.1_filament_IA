<?php

namespace App\Filament\Resources\Epps\Pages;

use App\Filament\Resources\Epps\EppResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * EPP: listado y edicion en la misma pantalla.
 *
 * Un catalogo es una lista plana; abrir una pagina aparte para cambiar un
 * precio seria un paso de mas. Se edita en una ventana sobre el listado.
 */
class GestionarEpp extends ManageRecords
{
    protected static string $resource = EppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo'),
        ];
    }
}
