<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de cotizaciones.
 *
 * Sin boton de crear mientras no exista el formulario por pasos: ofrecerlo
 * llevaria a un formulario incapaz de guardar una cotizacion completa.
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
        return [];
    }
}
