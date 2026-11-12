<?php

namespace App\Filament\Resources\Gerencias\Pages;

use App\Filament\Resources\Gerencias\GerenciaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Gerencias: listado y edicion en la misma pantalla.
 *
 * Un catalogo es una lista plana; abrir una pagina aparte para cambiar un
 * valor seria un paso de mas. Se edita en una ventana sobre el listado.
 */
class GestionarGerencia extends ManageRecords
{
    protected static string $resource = GerenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label("Nuevo"),
        ];
    }
}
