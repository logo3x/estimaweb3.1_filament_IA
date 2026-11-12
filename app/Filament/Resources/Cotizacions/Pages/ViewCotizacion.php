<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Dominio\CalcularCotizacion;
use App\Dominio\CotizacionCalculada;
use App\Dominio\Documentos\GeneradorDeOferta;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detalle de una cotizacion, con el precio recalculado en vivo.
 *
 * Los totales NO se leen de las columnas guardadas: se recalculan con el
 * dominio en cada visita. Treinta y dos cotizaciones de produccion arrastran
 * totales de versiones anteriores del codigo, asi que mostrar la columna
 * seria mostrar un numero viejo. Ademas, recalcular es la forma de que el
 * precio refleje siempre la regla vigente.
 */
class ViewCotizacion extends ViewRecord
{
    protected static string $resource = CotizacionResource::class;

    private ?CotizacionCalculada $calculo = null;

    public function getTitle(): string
    {
        return 'Cotizacion ' . $this->getRecord()->id;
    }

    /** Sin acciones mientras la edicion no exista. */
    protected function getHeaderActions(): array
    {
        return [
            /**
             * El documento se genera al vuelo con los totales del dominio,
             * no con las columnas guardadas: asi nunca se envia al cliente
             * un precio desactualizado.
             */
            Action::make('descargarOferta')
                ->label('Descargar oferta')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    try {
                        $archivo = GeneradorDeOferta::crear()->generar($this->getRecord());
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo generar la oferta')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();

                        return null;
                    }

                    return response()->download($archivo)->deleteFileAfterSend();
                }),

            EditAction::make(),
        ];
    }

    /**
     * El calculo, hecho una sola vez por visita.
     *
     * Sin la memoria, cada campo del infolist lo repetiria entero.
     */
    private function calculo(): CotizacionCalculada
    {
        return $this->calculo ??= CalcularCotizacion::crear()->para($this->getRecord());
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la oferta')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('id')->label('No.'),
                    TextEntry::make('codigo')->label('Código'),
                    TextEntry::make('estado')->label('Estado')->badge()->placeholder('Sin estado'),
                    TextEntry::make('cliente')->label('Cliente'),
                    TextEntry::make('contacto')->label('Contacto')->placeholder('—'),
                    TextEntry::make('cargo')->label('Cargo'),
                    TextEntry::make('nombre')->label('Servicio')->columnSpanFull(),
                    TextEntry::make('gerencia')->label('Gerencia')->badge()->placeholder('—'),
                    TextEntry::make('dias_dura_servicio')->label('Duración')->suffix(' días'),
                    TextEntry::make('estimado_inicio')->label('Inicio estimado')->date('d/m/Y'),
                ]),

            Section::make('Precio')
                ->description('Recalculado ahora mismo, no leído de la base')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('escenario_aiu')
                        ->label('Escenario')
                        ->badge()
                        ->state(fn (): string => $this->calculo()->parametros->escenario()),

                    TextEntry::make('porcentajes_aiu')
                        ->label('A / I / U')
                        ->state(function (): string {
                            $p = $this->calculo()->parametros;

                            return sprintf('%s%% / %s%% / %s%%', $p->administracion, $p->imprevistos, $p->utilidad);
                        }),

                    TextEntry::make('numero_items')
                        ->label('Ítems')
                        ->state(fn (): int => $this->getRecord()->items()->count()),

                    TextEntry::make('costo_directo')
                        ->label('Costo directo')
                        ->money('COP', locale: 'es_CO')
                        ->state(fn (): float => $this->calculo()->totales->costoDirecto),

                    TextEntry::make('aiu')
                        ->label('AIU')
                        ->money('COP', locale: 'es_CO')
                        ->state(fn (): float => $this->calculo()->totales->aiuEnPesos()),

                    TextEntry::make('subtotal')
                        ->label('Subtotal')
                        ->money('COP', locale: 'es_CO')
                        ->state(fn (): float => $this->calculo()->totales->subtotal),

                    TextEntry::make('iva')
                        ->label('IVA')
                        ->money('COP', locale: 'es_CO')
                        ->state(fn (): float => $this->calculo()->totales->iva),

                    TextEntry::make('total')
                        ->label('TOTAL')
                        ->money('COP', locale: 'es_CO')
                        ->weight('bold')
                        ->size('lg')
                        ->state(fn (): float => $this->calculo()->totales->total)
                        ->columnSpan(2),
                ]),

            /**
             * Recursos que quedaron fuera del precio.
             *
             * El sistema actual los descarta en silencio: una cotizacion con
             * quince recursos cargados puede dar cero sin mostrar nada.
             */
            Section::make('Avisos')
                ->icon('heroicon-o-exclamation-triangle')
                ->visible(fn (): bool => $this->calculo()->tieneAvisos())
                ->schema([
                    TextEntry::make('avisos')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->color('warning')
                        ->state(fn (): array => $this->calculo()->avisos),
                ]),

            Section::make('Costo por categoría')
                ->collapsible()
                ->schema([
                    Grid::make(1)->schema(
                        collect($this->calculo()->costoPorCategoria())
                            ->map(fn (float $monto, string $categoria): TextEntry => TextEntry::make('cat_' . $categoria)
                                ->label($categoria)
                                ->money('COP', locale: 'es_CO')
                                ->state($monto))
                            ->values()
                            ->all()
                    ),
                ]),

            Section::make('Aprobaciones')
                ->columns(2)
                ->collapsible()
                ->schema([
                    TextEntry::make('aprobacion1')
                        ->label('Técnica')
                        ->badge()
                        ->placeholder('Pendiente'),
                    TextEntry::make('aprobadorTecnico.name')
                        ->label('Aprobada por')
                        ->placeholder('—'),
                    TextEntry::make('aprobacion2')
                        ->label('Financiera')
                        ->badge()
                        ->placeholder('Pendiente'),
                    TextEntry::make('aprobadorFinanciero.name')
                        ->label('Aprobada por')
                        ->placeholder('—'),
                ]),
        ]);
    }
}
