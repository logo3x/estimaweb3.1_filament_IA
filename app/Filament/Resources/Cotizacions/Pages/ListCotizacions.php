<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de cotizaciones.
 *
 * Crear pide solo los datos de cabecera; los recursos se cargan despues en
 * la pantalla de edicion.
 */
class ListCotizacions extends ListRecords
{
    protected static string $resource = CotizacionResource::class;

    public function getTitle(): string
    {
        return 'Cotizaciones';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva cotización'),
        ];
    }
}
