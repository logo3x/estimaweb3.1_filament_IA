<?php

namespace App\Filament\Resources\ExamenVacunas\Pages;

use App\Filament\Resources\ExamenVacunas\ExamenVacunaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Examenes y vacunas: listado y edicion en la misma pantalla.
 *
 * Un catalogo es una lista plana; abrir una pagina aparte para cambiar un
 * valor seria un paso de mas. Se edita en una ventana sobre el listado.
 */
class GestionarExamenVacuna extends ManageRecords
{
    protected static string $resource = ExamenVacunaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label("Nuevo"),
        ];
    }
}
