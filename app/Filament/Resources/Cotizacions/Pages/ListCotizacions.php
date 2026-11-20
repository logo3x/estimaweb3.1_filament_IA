<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Dominio\ExportarCotizaciones;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Actions\Action;
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
            /**
             * Exporta lo que se esta viendo: con los filtros y la busqueda
             * del listado, y solo lo que el alcance deja ver.
             */
            Action::make('exportar')
                ->label('Exportar a Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $csv = ExportarCotizaciones::crear()->aCsv($this->getFilteredSortedTableQuery());

                    return response()->streamDownload(
                        function () use ($csv): void {
                            echo $csv;
                        },
                        ExportarCotizaciones::nombreDeArchivo(),
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    );
                }),

            CreateAction::make()->label('Nueva cotización'),
        ];
    }
}
