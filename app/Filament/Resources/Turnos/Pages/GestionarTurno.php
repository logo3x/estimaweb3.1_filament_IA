<?php

namespace App\Filament\Resources\Turnos\Pages;

use App\Filament\Resources\Turnos\TurnoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Turnos: listado y edicion en la misma pantalla.
 *
 * Un catalogo es una lista plana; abrir una pagina aparte para cambiar un
 * valor seria un paso de mas. Se edita en una ventana sobre el listado.
 */
class GestionarTurno extends ManageRecords
{
    protected static string $resource = TurnoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label("Nuevo"),
        ];
    }
}
