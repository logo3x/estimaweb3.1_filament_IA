<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Dominio\Aprobaciones\RelatoDelFlujo;
use App\Dominio\CalcularCotizacion;
use App\Dominio\CotizacionCalculada;
use App\Dominio\Documentos\ConversorAPdf;
use App\Dominio\Documentos\GeneradorDeOferta;
use App\Filament\Resources\Cotizacions\AccionesDeAprobacion;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
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
                ->visible(fn (): bool => $this->puedeGenerarDocumentos())
                ->label('Word')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    /** Esconder el boton no es protección. */
                    abort_unless($this->puedeGenerarDocumentos(), 403);

                    try {
                        $archivo = GeneradorDeOferta::crear()->generar($this->getRecord());
                    } catch (\Throwable $e) {
                        $this->avisarDelFallo($e);

                        return null;
                    }

                    return response()->download($archivo)->deleteFileAfterSend();
                }),

            /**
             * El PDF se obtiene convirtiendo el Word, asi que ambos muestran
             * las mismas cifras. El Word sigue siendo el documento
             * principal: la conversion pierde algo de fidelidad porque en un
             * hosting compartido no hay Word ni LibreOffice.
             */
            Action::make('descargarPdf')
                ->visible(fn (): bool => $this->puedeGenerarDocumentos())
                ->label('PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->action(function () {
                    /** Esconder el boton no es protección. */
                    abort_unless($this->puedeGenerarDocumentos(), 403);

                    try {
                        $archivo = ConversorAPdf::crear()->desdeCotizacion($this->getRecord());
                    } catch (\Throwable $e) {
                        $this->avisarDelFallo($e);

                        return null;
                    }

                    return response()->download($archivo)->deleteFileAfterSend();
                }),

            EditAction::make()
                /** Lo aprobado no se edita; el boton no debe ni aparecer. */
                ->visible(fn (): bool => $this->getRecord()->puedeSerEditadaPor(auth()->user())),

            ...AccionesDeAprobacion::todas(),
        ];
    }

    /**
     * Un fallo al generar el documento se explica, no se traga.
     *
     * Se captura `Throwable` y no `RuntimeException`: PhpWord y DomPDF
     * lanzan excepciones que extienden `\Exception` a secas, de modo que
     * una plantilla corrupta, un ZIP no escribible o una falta de memoria
     * se escapaban y daban pantalla de error en vez de este aviso.
     *
     * El mensaje tecnico va al registro, no a la pantalla: puede contener
     * rutas del servidor, y a quien cotiza no le dice nada.
     */
    private function avisarDelFallo(\Throwable $error): void
    {
        Log::error('No se pudo generar el documento de una cotización', [
            'cotizacion' => $this->getRecord()->id,
            'error' => $error->getMessage(),
            'tipo' => $error::class,
        ]);

        Notification::make()
            ->danger()
            ->title('No se pudo generar el documento')
            ->body($this->explicar($error))
            ->persistent()
            ->send();
    }

    /**
     * Traduce el fallo a algo accionable.
     *
     * Los dos motivos habituales tienen solucion conocida; para el resto
     * se pide avisar, porque no hay nada que el usuario pueda hacer.
     */
    private function explicar(\Throwable $error): string
    {
        if ($error instanceof \RuntimeException) {
            /** Son los nuestros: ya vienen redactados para quien cotiza. */
            return $error->getMessage();
        }

        return 'Hubo un problema al armar el archivo. Avise a sistemas: '
            . 'el detalle quedó registrado.';
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
                    /**
                     * El estado se deriva del flujo, no se lee de la columna
                     * 'estado': en produccion esta vacia en 46 de 48 filas.
                     */
                    TextEntry::make('estado_aprobacion')
                        ->label('Estado')
                        ->badge()
                        ->color(fn (): string => $this->getRecord()->estadoAprobacion()->color())
                        ->state(fn (): string => $this->getRecord()->estadoAprobacion()->etiquetaCorta())
                        ->helperText(fn (): string => $this->getRecord()->estadoAprobacion()->explicacion()),
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

            /*
             * El mismo flujo que en la edicion.
             *
             * Antes solo estaba en la pantalla de editar, de modo que quien
             * NO puede editar -justo quien mas necesita saber a quien le
             * toca y por que esta bloqueada- veia diez campos sueltos con
             * los valores crudos de la base.
             */
            Section::make('Aprobación')
                ->icon('heroicon-o-check-badge')
                ->schema([
                    \Filament\Schemas\Components\View::make('filament.flujo-de-aprobacion')
                        ->columnSpanFull()
                        ->viewData(fn (): array => $this->datosDelFlujo()),
                ]),
        ]);
    }

    /**
     * Si quien mira puede emitir la oferta al cliente.
     *
     * La oferta lleva membrete y firma del Presidente: es el documento
     * formal de la empresa, no un borrador. El sistema actual lo reserva a
     * `isAdmin()` en `AiuStep.php:284`.
     */
    private function puedeGenerarDocumentos(): bool
    {
        return (new \App\Dominio\Permisos\Permisos())
            ->puedeElActual(\App\Dominio\Permisos\Capacidad::GenerarDocumentos);
    }

    /**
     * Lo que necesita la vista del flujo.
     *
     * @return array<string, mixed>
     */
    private function datosDelFlujo(): array
    {
        $relato = RelatoDelFlujo::de($this->getRecord());
        $estado = $relato->estado();

        return [
            'pasos' => $relato->pasos(),
            'resumen' => $relato->resumen(),
            'etiqueta' => $estado->etiqueta(),
            'color' => $estado->color(),
            'aQuienLeToca' => $relato->aQuienLeToca(),
            'leTocaAlQueMira' => $relato->leTocaA(auth()->user()),
        ];
    }

}
