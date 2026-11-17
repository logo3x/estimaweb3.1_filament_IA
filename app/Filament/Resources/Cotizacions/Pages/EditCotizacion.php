<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Dominio\Aprobaciones\RelatoDelFlujo;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Dominio\CalcularCotizacion;
use App\Dominio\Documentos\GeneradorDeOferta;
use App\Filament\Resources\Cotizacions\AccionesDeAprobacion;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

/**
 * Edicion de una cotizacion.
 *
 * Los datos de cabecera arriba y los recursos en pestanas debajo, una por
 * categoria. Las pestanas son Relation Managers de Filament, de modo que cada
 * una sabe a que cotizacion pertenece sin necesidad de guardar el id en la
 * sesion, que es como lo resuelve el sistema actual.
 */
class EditCotizacion extends EditRecord
{
    protected static string $resource = CotizacionResource::class;

    /** El calculo de la cotizacion, memorizado por carga. */
    private ?\App\Dominio\CotizacionCalculada $calculo = null;

    public function getTitle(): string
    {
        return 'Cotización ' . $this->getRecord()->id;
    }

    /**
     * Los botones de la cabecera, por orden de importancia.
     *
     * Antes eran seis en fila, tres de ellos en amarillo compitiendo por la
     * atencion y "Borrar" en rojo pegado a la accion principal, facil de
     * pulsar por error.
     *
     * Ahora queda a la vista solo lo que se usa a diario: la accion del
     * flujo que toque en este momento -enviar, aprobar, rechazar- y
     * descargar la oferta. El resto se recoge en un menu.
     */
    protected function getHeaderActions(): array
    {
        return [
            ...AccionesDeAprobacion::todas(),

            $this->descargarOferta(),

            ActionGroup::make([
                $this->recalcular(),
                ViewAction::make()->label('Ver detalle')->icon('heroicon-o-eye'),

                /** Separado del resto: borrar arrastra items y recursos. */
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Borrar cotización')
                        ->requiresConfirmation()
                        ->modalDescription('Se borrarán también todos sus ítems, recursos y asignaciones.')
                        /** Lo que ya se aprobo no se borra. */
                        ->visible(fn (): bool => $this->getRecord()->puedeSerEditadaPor(auth()->user())),
                ])->dropdown(false),
            ])
                ->label('Más acciones')
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }

    /**
     * Recalcular y guardar los totales.
     *
     * La pantalla de consulta siempre recalcula en vivo, pero el listado
     * lee las columnas guardadas: esta accion las pone al dia despues de
     * cambiar recursos.
     */
    private function recalcular(): Action
    {
        return Action::make('recalcular')
            ->label('Recalcular totales')
            ->icon('heroicon-o-calculator')
            ->action(function (): void {
                $resultado = CalcularCotizacion::crear()->para($this->getRecord());

                $this->getRecord()->update([
                    'valor_total' => $resultado->totales->costoDirecto,
                    'valor_total_con_aiu' => $resultado->totales->subtotal,
                    'aiu_administracion' => $resultado->totales->administracion,
                    'aiu_imprevistos' => $resultado->totales->imprevistos,
                    'aiu_utilidad' => $resultado->totales->utilidad,
                    'aiu_iva' => $resultado->totales->iva,
                    'aiu_valortotal_con_iva' => $resultado->totales->total,
                ]);

                $aviso = Notification::make()
                    ->success()
                    ->title('Totales actualizados')
                    ->body('Total: $' . number_format($resultado->totales->total, 2, ',', '.'));

                if ($resultado->tieneAvisos()) {
                    $aviso->warning()
                        ->title('Totales actualizados, con avisos')
                        ->body(count($resultado->avisos) . ' recurso(s) no se pudieron calcular. Revise la pantalla de consulta.');
                }

                $aviso->send();
            });
    }

    /**
     * Descarga la oferta en Word.
     *
     * El documento se genera al vuelo con los totales del dominio, no con
     * las columnas guardadas: asi nunca se envia al cliente un precio
     * desactualizado.
     */
    private function descargarOferta(): Action
    {
        return Action::make('descargarOferta')
            ->label('Descargar oferta')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->outlined()
            /**
             * La oferta sale con membrete y firma del Presidente: es el
             * documento formal de la empresa. El sistema actual la reserva
             * a `isAdmin()` -Administrador y super_admin-.
             */
            ->visible(fn (): bool => $this->puedeGenerarDocumentos())
            ->action(function () {
                abort_unless($this->puedeGenerarDocumentos(), 403, Capacidad::GenerarDocumentos->motivoDelBloqueo());

                try {
                    $archivo = GeneradorDeOferta::crear()->generar($this->getRecord());
                } catch (\Throwable $e) {
                    /**
                     * `Throwable` y no `RuntimeException`: PhpWord lanza
                     * excepciones que extienden `\Exception` a secas, asi
                     * que una plantilla corrupta se escapaba de este catch
                     * y daba pantalla de error.
                     */
                    Log::error('No se pudo generar la oferta', [
                        'cotizacion' => $this->getRecord()->id,
                        'error' => $e->getMessage(),
                        'tipo' => $e::class,
                    ]);

                    Notification::make()
                        ->danger()
                        ->title('No se pudo generar la oferta')
                        ->body($e instanceof \RuntimeException
                            ? $e->getMessage()
                            : 'Hubo un problema al armar el archivo. Avise a sistemas: el detalle quedó registrado.')
                        ->persistent()
                        ->send();

                    return null;
                }

                return response()->download($archivo)->deleteFileAfterSend();
            });
    }

    /**
     * No se entra a editar lo que el flujo tiene bloqueado.
     *
     * Se sustituye la autorizacion de Filament, que respondería un 403
     * seco. Aqui interesa lo contrario: llevar al detalle y explicar por
     * que no se puede -esta en aprobacion, ya se aprobo-, que es
     * informacion util y no un error del usuario.
     *
     * Va en authorizeAccess() y no en mount() porque Filament lo llama
     * tambien en cada peticion de Livewire: puesto solo en mount(), una
     * peticion posterior volveria a dar el 403.
     */
    protected function authorizeAccess(): void
    {
        $cotizacion = $this->getRecord();

        /**
         * El alcance por gerencia se comprueba aparte y con un 404.
         *
         * Al sustituir authorizeAccess() se deja de pasar por la policy, que
         * es donde vive esa regla. Hoy coincide con lo que decide el flujo
         * de aprobacion, pero por casualidad: si manana se relaja una rama
         * de esas reglas, la edicion dejaria de comprobar la gerencia sin
         * que nadie lo note.
         *
         * Va antes que la redireccion explicativa a proposito: un aviso de
         * "esta en aprobacion" ya confirmaria que la cotizacion existe.
         */
        abort_unless(
            (new \App\Dominio\Permisos\AlcanceDeCotizaciones())
                ->puedeVer($cotizacion, auth()->user()),
            404
        );

        if ($cotizacion->puedeSerEditadaPor(auth()->user())) {
            return;
        }

        $motivo = (new \App\Dominio\Aprobaciones\ReglasDeAprobacion())
            ->motivoDelBloqueo($cotizacion, auth()->user());

        Notification::make()
            ->warning()
            ->title('Cotización bloqueada')
            ->body($motivo)
            ->persistent()
            ->send();

        $this->redirect(CotizacionResource::getUrl('view', ['record' => $cotizacion]));
    }

    /**
     * La categoria adicional que se esta viendo.
     *
     * Vive en la direccion para que no se pierda al guardar ni al volver
     * atras en el navegador.
     */
    #[\Livewire\Attributes\Url(as: 'categoria')]
    public ?string $categoriaActiva = null;

    /**
     * Las siete categorias adicionales, con su menu debajo del principal.
     *
     * Al abrir "Categorias adicionales" aparece una segunda fila de
     * pestañas con las siete, y debajo la tabla de la elegida.
     *
     * **Solo se monta el componente de la categoria activa.** Los intentos
     * anteriores los montaban los siete a la vez y Filament acababa
     * renderizando el mismo en dos paneles: al cambiar de pestaña se
     * seguia viendo la tabla de la primera. Con uno solo no hay confusion
     * posible, y de paso se carga una tabla en vez de siete.
     */
    public function getRelationManagerTabComponent(string | int $key): ?\Filament\Schemas\Components\Tabs\Tab
    {
        $gestor = $this->getCachedRelationManagers()[$key] ?? null;

        if (! ($gestor instanceof \Filament\Resources\RelationManagers\RelationGroup)) {
            return parent::getRelationManagerTabComponent($key);
        }

        $gestor->ownerRecord($this->getRecord())->pageClass(static::class);

        $clases = array_values($gestor->getManagers());
        $activa = $this->posicionDeLaCategoriaActiva(count($clases));
        $claseActiva = $clases[$activa];

        return $gestor->getTabComponent()->schema([
            /** El menu: una fila de botones, como las pestañas de arriba. */
            \Filament\Schemas\Components\Actions::make(
                array_map(
                    fn (string $clase, int $posicion): Action => $this->botonDeCategoria($posicion, $posicion === $activa),
                    $clases,
                    array_keys($clases),
                )
            )->columnSpanFull(),

            /** Y debajo, solo la tabla de la elegida. */
            \Filament\Schemas\Components\Livewire::make($claseActiva, [
                ...$this->getRelationManagerLivewireData(),
                ...$claseActiva::getDefaultProperties(),
            ])->key('categoria-activa-' . $activa),
        ]);
    }

    /** Cual de las siete se esta viendo, con el primero por defecto. */
    private function posicionDeLaCategoriaActiva(int $cuantas): int
    {
        $pedida = (int) ($this->categoriaActiva ?? 0);

        return ($pedida >= 0 && $pedida < $cuantas) ? $pedida : 0;
    }

    /** Un boton del menu de categorias. */
    private function botonDeCategoria(int $posicion, bool $activa): Action
    {
        [$titulo, $icono] = \App\Filament\Resources\Cotizacions\RelationManagers\CategoriasAdicionales::etiqueta($posicion);

        return Action::make("categoria{$posicion}")
            ->label($titulo)
            ->icon($icono)
            ->color($activa ? 'primary' : 'gray')
            ->outlined(! $activa)
            ->size('sm')
            ->action(fn () => $this->categoriaActiva = (string) $posicion);
    }

    /**
     * "Calculo de AIU" cierra la barra de pestañas.
     *
     * Es donde se cuadra todo al final: el margen, el precio que sale con
     * el, los documentos que se le mandan al cliente y el ciclo de
     * aprobacion. Igual que en el sistema actual, que lo reune en su
     * ultimo paso.
     *
     * Antes estaba repartido -el AIU entre los datos de cabecera, los
     * documentos en los botones de arriba, las aprobaciones solo en el
     * detalle- y habia que recordar tres sitios para cerrar una oferta.
     *
     * @return array<string | int, \Filament\Schemas\Components\Tabs\Tab>
     */
    public function getTabComponents(): array
    {
        return [
            ...parent::getTabComponents(),
            'aiu' => \Filament\Schemas\Components\Tabs\Tab::make('Cálculo de AIU')
                ->icon('heroicon-o-calculator')
                ->schema([
                    $this->margen(),
                    $this->resumenDelPrecio(),
                    $this->repartoPorCategoria(),
                    $this->resumenDeLaOferta(),
                    $this->aprobacion(),
                    $this->documentos(),
                ]),
        ];
    }

    /**
     * El margen configurado, con el boton para cambiarlo.
     *
     * Se muestra aqui y se edita en un modal. El formulario del margen
     * cuelga de la relacion `aiu`, y eso solo funciona dentro del
     * formulario de un registro: en esta pestaña no hay ninguno, asi que
     * el modal se lo proporciona.
     */
    private function margen(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Configuración del AIU')
            ->icon('heroicon-o-adjustments-horizontal')
            ->description('Administración, imprevistos y utilidad: lo que convierte el costo en precio.')
            ->columnSpanFull()
            ->columns(4)
            ->schema([
                $this->porcentaje('Administración', fn (): float => $this->parametros()->administracion),
                $this->porcentaje('Imprevistos', fn (): float => $this->parametros()->imprevistos),
                $this->porcentaje('Utilidad', fn (): float => $this->parametros()->utilidad),

                \Filament\Forms\Components\Placeholder::make('factor_aiu')
                    ->label('Multiplica el costo por')
                    ->content(fn (): string => number_format($this->parametros()->factor(), 4, ',', '.')),

                \Filament\Forms\Components\Placeholder::make('explicacion_aiu')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->content(fn (): string => $this->explicarElAiu()),

                \Filament\Schemas\Components\Actions::make([
                    $this->cambiarMargenAction(),
                ])->columnSpanFull(),
            ]);
    }

    /** Un porcentaje con su simbolo. */
    private function porcentaje(string $etiqueta, callable $valor): \Filament\Forms\Components\Placeholder
    {
        return \Filament\Forms\Components\Placeholder::make(
            'pct_' . \Illuminate\Support\Str::slug($etiqueta, '_')
        )
            ->label($etiqueta)
            ->content(fn (): string => rtrim(rtrim(number_format($valor(), 2, ',', '.'), '0'), ',') . ' %');
    }

    /**
     * Cambia el margen desde un modal.
     *
     * Las banderas se guardan como texto 'SI'/'NO' porque asi estan los
     * datos de produccion; el interruptor trabaja con booleanos y se
     * traducen en los dos sentidos.
     */
    public function cambiarMargenAction(): Action
    {
        return Action::make('cambiarMargen')
            ->label('Cambiar el margen')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->modalHeading('Configuración del AIU')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Guardar')
            /**
             * Cambiar el margen cambia el precio de la oferta.
             *
             * El sistema actual lo reserva a Administrador y Gerente
             * (`AiuStep::handle()`: «Solo Administradores y Gerentes pueden
             * configurar el AIU»). Aqui se habia perdido.
             */
            ->visible(fn (): bool => $this->puedeConfigurarElMargen())
            ->fillForm(function (): array {
                $aiu = $this->getRecord()->aiu;

                return [
                    'administracion' => $aiu?->administracion ?? 0,
                    'imprevistos' => $aiu?->imprevistos ?? 0,
                    'utilidad' => $aiu?->utilidad ?? 0,
                    'contrato_obra' => (bool) $aiu?->esContratoDeObra(),
                    'mostrar_aiu' => (bool) $aiu?->muestraAiuDesglosado(),
                    'contrato_actual' => (bool) $aiu?->esContratoActual(),
                ];
            })
            ->schema([
                \Filament\Schemas\Components\Grid::make(3)->schema([
                    \Filament\Forms\Components\TextInput::make('administracion')
                        ->label('Administración')
                        ->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
                    \Filament\Forms\Components\TextInput::make('imprevistos')
                        ->label('Imprevistos')
                        ->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
                    \Filament\Forms\Components\TextInput::make('utilidad')
                        ->label('Utilidad')
                        ->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
                ]),

                \Filament\Forms\Components\Toggle::make('contrato_obra')
                    ->label('Contrato de obra')
                    ->helperText('El IVA grava solo la utilidad, no el total.'),

                \Filament\Forms\Components\Toggle::make('mostrar_aiu')
                    ->label('Mostrar AIU desglosado')
                    ->helperText('El cliente ve A, I y U como líneas aparte.'),

                \Filament\Forms\Components\Toggle::make('contrato_actual')
                    ->label('Sobre contrato actual'),
            ])
            ->action(function (array $data): void {
                /** Esconder el boton no es protección: una peticion de Livewire llega igual. */
                abort_unless($this->puedeConfigurarElMargen(), 403, Capacidad::ConfigurarElMargen->motivoDelBloqueo());

                $this->getRecord()->aiu()->updateOrCreate(
                    ['id_cotizacion' => $this->getRecord()->id],
                    [
                        'administracion' => (float) $data['administracion'],
                        'imprevistos' => (float) $data['imprevistos'],
                        'utilidad' => (float) $data['utilidad'],
                        /** Texto, no booleano: asi estan los datos de produccion. */
                        'contrato_obra' => $data['contrato_obra'] ? 'SI' : 'NO',
                        'mostrar_aiu' => $data['mostrar_aiu'] ? 'SI' : 'NO',
                        'contrato_actual' => $data['contrato_actual'] ? 'SI' : 'NO',
                    ],
                );

                /** El precio de arriba se recalcula con el margen nuevo. */
                $this->calculo = null;

                Notification::make()
                    ->success()
                    ->title('Margen actualizado')
                    ->body('El precio de abajo ya refleja el cambio.')
                    ->send();
            });
    }

    /**
     * De donde sale el precio, por tipo de recurso.
     *
     * Responde a "cuanto de esto es personal y cuanto herramientas", que es
     * la primera pregunta al revisar una cotizacion ajena.
     */
    private function repartoPorCategoria(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('De dónde sale el costo')
            ->icon('heroicon-o-chart-pie')
            ->description('El costo directo repartido por tipo de recurso.')
            ->columnSpanFull()
            ->collapsible()
            ->schema([
                \Filament\Forms\Components\Placeholder::make('reparto')
                    ->label('')
                    ->columnSpanFull()
                    ->content(function (): \Illuminate\Support\HtmlString {
                        $porCategoria = $this->calculo()->costoPorCategoria();

                        if ($porCategoria === []) {
                            return new \Illuminate\Support\HtmlString(
                                '<p class="fi-resumen-vacio">Todavía no hay recursos cargados.</p>'
                            );
                        }

                        $total = array_sum($porCategoria);

                        $filas = '';

                        foreach ($porCategoria as $categoria => $monto) {
                            $porcentaje = $total > 0 ? ($monto / $total) * 100 : 0;

                            $filas .= sprintf(
                                '<tr><td>%s</td><td class="fi-resumen-der">%s</td>'
                                . '<td class="fi-resumen-der">%s %%</td>'
                                . '<td><span class="fi-resumen-barra" style="width:%.1f%%"></span></td></tr>',
                                e($categoria),
                                e($this->pesos($monto)),
                                e(number_format($porcentaje, 1, ',', '.')),
                                $porcentaje,
                            );
                        }

                        return new \Illuminate\Support\HtmlString(
                            '<table class="fi-resumen-tabla fi-resumen-reparto">' . $filas . '</table>'
                        );
                    }),
            ]);
    }

    /**
     * La tabla que vera el cliente, con su desglose.
     *
     * Es la misma que lleva el documento Word, calculada con el dominio: si
     * aqui cuadra, el documento cuadra. Sirve para revisar antes de enviar.
     *
     * Arranca plegada: son tantas filas como recursos tenga la cotizacion.
     */
    private function resumenDeLaOferta(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Resumen de la oferta')
            ->icon('heroicon-o-document-magnifying-glass')
            ->description('Lo que verá el cliente, con el detalle de cada ítem.')
            ->columnSpanFull()
            ->collapsible()
            ->collapsed()
            ->schema([
                \Filament\Schemas\Components\View::make('filament.resumen-de-la-oferta')
                    ->columnSpanFull()
                    ->viewData(fn (): array => $this->datosDelResumen()),
            ]);
    }

    /**
     * Los items con sus lineas de costo y los totales.
     *
     * @return array{items: array<int, mixed>, totales: array<string, float>, pesos: callable}
     */
    private function datosDelResumen(): array
    {
        $calculo = $this->calculo();
        $totales = $calculo->totales;

        $lineasPorItem = collect($calculo->detalle)->groupBy('itemId');

        $items = $this->getRecord()->items()->orderBy('id')->get()
            ->map(fn ($item): array => [
                'nombre' => $item->nombre,
                'unidad' => $item->unidad ?: 'UND',
                'cantidad' => (int) ($item->cantidad ?: 1),
                'unitario' => $calculo->valorUnitarioPorItem[$item->id] ?? 0.0,
                'parcial' => ($calculo->valorUnitarioPorItem[$item->id] ?? 0.0)
                    * (int) ($item->cantidad ?: 1),
                'lineas' => $lineasPorItem->get($item->id, collect())->all(),
            ])
            ->all();

        return [
            'items' => $items,
            'totales' => array_filter([
                'Costo directo' => $totales->costoDirecto,
                'Administración' => $totales->administracion,
                'Imprevistos' => $totales->imprevistos,
                'Utilidad' => $totales->utilidad,
                'Subtotal' => $totales->subtotal,
                'IVA (19 %)' => $totales->iva,
                'TOTAL' => $totales->total,
            ], fn (float $valor, string $etiqueta): bool => $valor > 0 || $etiqueta === 'TOTAL', ARRAY_FILTER_USE_BOTH),
            'pesos' => fn (float $valor): string => $this->pesos($valor),
        ];
    }

    /** Un importe en pesos. */
    private function pesos(float $valor): string
    {
        return '$ ' . number_format($valor, 0, ',', '.');
    }

    /** Los parametros del AIU con que se esta calculando. */
    private function parametros(): \App\Dominio\Aiu\ParametrosAiu
    {
        return $this->calculo()->parametros;
    }

    /** Explica en una linea que hace el margen configurado. */
    private function explicarElAiu(): string
    {
        $parametros = $this->parametros();

        if ($parametros->porcentajeTotal() <= 0) {
            return 'Sin AIU: el precio es el costo directo más IVA.';
        }

        return sprintf(
            '%s El AIU %s.',
            $parametros->esContratoDeObra
                ? 'El IVA del 19% grava solo la utilidad.'
                : 'El IVA del 19% grava el total con AIU.',
            $parametros->muestraAiuDesglosado
                ? 'aparece desglosado y los ítems muestran su costo directo'
                : 'va incorporado en el precio de cada ítem'
        );
    }

    /**
     * El precio que sale con el margen configurado.
     *
     * Recalculado en vivo, no leido de las columnas guardadas: treinta y
     * dos cotizaciones de produccion arrastran totales de versiones
     * anteriores del codigo.
     */
    private function resumenDelPrecio(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Precio resultante')
            ->icon('heroicon-o-banknotes')
            ->description('Recalculado ahora mismo con los recursos cargados.')
            ->columnSpanFull()
            ->columns(4)
            ->schema([
                $this->cifra('Costo directo', fn (): float => $this->calculo()->totales->costoDirecto),
                $this->cifra('AIU', fn (): float => $this->calculo()->totales->aiuEnPesos()),
                $this->cifra('Subtotal', fn (): float => $this->calculo()->totales->subtotal),
                $this->cifra('IVA', fn (): float => $this->calculo()->totales->iva),
                $this->cifra('TOTAL', fn (): float => $this->calculo()->totales->total)
                    ->columnSpan(2),

                \Filament\Forms\Components\Placeholder::make('escenario')
                    ->label('Escenario')
                    ->columnSpan(2)
                    ->content(fn (): string => $this->calculo()->parametros->escenario()),

                /**
                 * Recursos que quedaron fuera del precio.
                 *
                 * El sistema actual los descarta en silencio: una
                 * cotizacion con quince recursos cargados puede dar cero
                 * sin mostrar nada.
                 */
                \Filament\Forms\Components\Placeholder::make('avisos_calculo')
                    ->label('Avisos del cálculo')
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->calculo()->tieneAvisos())
                    ->content(fn (): string => implode(' · ', array_slice($this->calculo()->avisos, 0, 5))),

                \Filament\Schemas\Components\Actions::make([
                    Action::make('guardarTotales')
                        ->label('Guardar estos totales en la cotización')
                        ->icon('heroicon-o-calculator')
                        ->color('gray')
                        ->action(fn () => $this->recalcular()->call()),
                ])->columnSpanFull(),
            ]);
    }

    /** Un importe con formato de pesos. */
    private function cifra(string $etiqueta, callable $valor): \Filament\Forms\Components\Placeholder
    {
        return \Filament\Forms\Components\Placeholder::make(
            'cifra_' . \Illuminate\Support\Str::slug($etiqueta, '_')
        )
            ->label($etiqueta)
            ->content(fn (): string => '$ ' . number_format($valor(), 2, ',', '.'));
    }

    /** Los documentos y el ciclo de aprobacion, en el mismo sitio. */
    /**
     * El ciclo de aprobacion, con el estado actual a la vista.
     *
     * Tres pasos: la escribe quien cotiza, la revisa el gerente de su
     * gerencia y la cierra la administracion. Verlos juntos responde a
     * "por que no puedo editar esto" y "a quien le toca ahora", que es lo
     * que se pregunta quien abre una cotizacion ajena.
     */
    private function aprobacion(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Aprobación')
            ->icon('heroicon-o-check-badge')
            ->columnSpanFull()
            ->schema([
                \Filament\Schemas\Components\View::make('filament.flujo-de-aprobacion')
                    ->columnSpanFull()
                    ->viewData(fn (): array => $this->datosDelFlujo()),

                \Filament\Schemas\Components\Actions::make(AccionesDeAprobacion::todas())
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Lo que necesita la vista del flujo.
     *
     * El relato lo arma el dominio; aqui solo se le pasa a la plantilla.
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

    /** Si quien mira puede cambiar el margen, que es cambiar el precio. */
    private function puedeConfigurarElMargen(): bool
    {
        return (new Permisos())->puedeElActual(Capacidad::ConfigurarElMargen);
    }

    /** Si quien mira puede emitir la oferta al cliente. */
    private function puedeGenerarDocumentos(): bool
    {
        return (new Permisos())->puedeElActual(Capacidad::GenerarDocumentos);
    }

    /** El documento que se le manda al cliente. */
    private function documentos(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Documentos')
            ->icon('heroicon-o-document-arrow-down')
            ->description('Se generan con los totales de arriba, no con los guardados.')
            ->columnSpanFull()
            ->schema([
                \Filament\Schemas\Components\Actions::make([
                    $this->descargarOferta(),
                ])->columnSpanFull(),
            ]);
    }

    /** El calculo, hecho una sola vez por carga. */
    private function calculo(): \App\Dominio\CotizacionCalculada
    {
        return $this->calculo ??= CalcularCotizacion::crear()->para($this->getRecord());
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Datos guardados';
    }

    /**
     * Las pestanas se mantienen visibles mientras se edita.
     *
     * Por defecto Filament las pone debajo del formulario; aqui conviene que
     * sean lo primero tras guardar los datos, porque es donde esta el grueso
     * del trabajo.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos de la cotización';
    }
}
