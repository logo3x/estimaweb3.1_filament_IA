<?php

namespace App\Filament\Resources\DatosComunicacions\Pages;

use App\Filament\Resources\DatosComunicacions\DatosComunicacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Comunicaciones: listado y edicion en la misma pantalla.
 *
 * Un catalogo es una lista plana; abrir una pagina aparte para cambiar un
 * valor seria un paso de mas. Se edita en una ventana sobre el listado.
 */
class GestionarDatosComunicacion extends ManageRecords
{
    protected static string $resource = DatosComunicacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label("Nuevo"),
        ];
    }
}
