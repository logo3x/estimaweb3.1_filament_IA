<?php

namespace App\Filament\Resources\DatosHerramientas\Pages;

use App\Filament\Resources\DatosHerramientas\DatosHerramientaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Herramientas: listado y edicion en la misma pantalla.
 *
 * Un catalogo es una lista plana; abrir una pagina aparte para cambiar un
 * precio seria un paso de mas. Se edita en una ventana sobre el listado.
 */
class GestionarDatosHerramienta extends ManageRecords
{
    protected static string $resource = DatosHerramientaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo'),
        ];
    }
}
