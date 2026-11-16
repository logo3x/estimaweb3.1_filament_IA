<?php

namespace App\Filament\Resources\Cotizacions\Schemas;

use App\Models\Cotizacion;
use App\Models\Gerencia;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Datos de cabecera de una cotizacion.
 *
 * Es el primer paso: hasta que estos datos no esten guardados no se pueden
 * cargar recursos, porque todos cuelgan de la cotizacion.
 *
 * Va en cuatro secciones -a quien se le cotiza, que se cotiza, en que
 * condiciones y con que margen- y no en pestañas: esta pantalla ya vive
 * dentro de las pestañas de la cotizacion, y anidar un segundo nivel
 * obliga a recordar en cual de los dos se esta.
 *
 * La del AIU arranca plegada: solo se toca cuando hay que cambiar el
 * margen, no en cada edicion.
 */
class CotizacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            self::datosDeLaOferta(),
            self::observaciones(),
        ]);
    }

    /**
     * Los datos que van al documento, en el orden del sistema actual.
     *
     * Tres filas: que se cotiza y a quien, con quien se habla, y en que
     * condiciones. Es el orden que conoce quien cotiza a diario.
     */
    private static function datosDeLaOferta(): Section
    {
        return Section::make('Datos de la cotización')
            ->icon('heroicon-o-document-text')
            ->columnSpanFull()
            /**
             * Doce columnas para repartir por contenido.
             *
             * Con tres iguales, "Duración" -dos digitos- ocupaba lo mismo
             * que "Alcance", que es una frase larga. Aqui cada campo pide
             * lo que necesita.
             */
            ->columns(12)
            ->schema([
                /**
                 * El codigo, a la derecha de la cabecera.
                 *
                 * Es como el area comercial identifica la oferta, asi que
                 * conviene verlo al entrar; una caja propia para una linea
                 * de texto desperdiciaba toda una franja de pantalla.
                 */
                Placeholder::make('codigo_visible')
                    ->label('Código')
                    ->columnSpan(4)
                    ->content(fn (?Cotizacion $record): string => $record?->codigo
                        ?: 'Se asignará al guardar'),

                TextInput::make('codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(4)
                    ->hidden(fn (?Cotizacion $record): bool => $record !== null)
                    ->helperText('Por ejemplo: EOEP-BOG-20261113-001.'),

                TextInput::make('nombre')
                    ->label('Nombre del servicio')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(8)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Nombre descriptivo del servicio que se va a cotizar.'),

                TextInput::make('cliente')
                    ->label('Cliente')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(4)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Razón social del cliente al que se le presenta la cotización.'),

                Textarea::make('alcance')
                    ->label('Alcance')
                    ->required()
                    ->rows(2)
                    ->autosize()
                    ->columnSpan(12)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Descripción general del alcance del servicio a prestar.'),

                TextInput::make('contacto')
                    ->label('Contacto')
                    ->maxLength(255)
                    ->columnSpan(4)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Nombre de la persona de contacto por parte del cliente.'),

                TextInput::make('cargo')
                    ->label('Cargo')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(4)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Cargo del contacto del cliente.'),

                /**
                 * La columna se llama `referencia` pero se muestra como
                 * "Asunto", igual que en el sistema actual: es el nombre
                 * con que el cliente pide el servicio.
                 */
                TextInput::make('referencia')
                    ->label('Asunto')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(4)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Nombre con el cual el cliente realiza la solicitud inicial del requerimiento y/o servicio.'),

                TextInput::make('vigencia')
                    ->label('Vigencia')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Periodo de validez de la cotización. Ejemplo: 30 días.'),

                /**
                 * La duracion se copia a cada EPP y examen, y alli divide
                 * su costo. Un cero hacia que la cotizacion entera no se
                 * pudiera abrir en el sistema actual.
                 */
                TextInput::make('dias_dura_servicio')
                    ->label('Duración')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->suffix('días')
                    ->columnSpan(2)
                    ->validationMessages([
                        'min' => 'Al menos 1 día: reparte el costo de EPP y exámenes.',
                    ])
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Días que dura el servicio. Reparte el costo de EPP y exámenes.'),

                DatePicker::make('estimado_inicio')
                    ->label('Fecha estimada')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->columnSpan(3)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Fecha estimada de inicio de la ejecución del servicio.'),

                TextInput::make('forma_pago')
                    ->label('Forma de pago')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(5)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Condiciones de pago. Ejemplo: 30 días después de radicada la factura.'),

                /**
                 * La gerencia es texto libre, no una lista cerrada.
                 *
                 * En los datos reales esta columna guarda NUMEROS (4, 6, 7,
                 * 8) que no corresponden al catalogo actual, donde solo hay
                 * "Zona 1" a "Zona 5". Son ids de una version anterior de
                 * esa tabla: un Select cerrado rechazaria todas las
                 * cotizaciones existentes al editarlas.
                 */
                TextInput::make('gerencia')
                    ->label('Gerencia')
                    ->maxLength(255)
                    ->columnSpan(3)
                    ->datalist(fn (): array => Gerencia::query()
                        ->orderBy('nombre')
                        ->pluck('nombre')
                        ->all())
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Zona o gerencia que presta el servicio.'),

                TextInput::make('otras_condiciones')
                    ->label('Otras condiciones')
                    ->maxLength(255)
                    ->columnSpan(9)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Condiciones adicionales que deban constar en la oferta.'),
            ]);
    }

    /**
     * Las observaciones, con formato.
     *
     * Van al documento tal cual, con sus titulos y sus listas: son las
     * premisas que el cliente debe tener en cuenta. Con un campo de texto
     * plano habria que escribirlas de corrido y se perderia la estructura
     * que el generador del Word sabe reproducir.
     */
    private static function observaciones(): Section
    {
        return Section::make('Observaciones')
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->description('Premisas que el cliente debe tener en cuenta. Aparecen en la oferta.')
            ->columnSpanFull()
            ->schema([
                RichEditor::make('observaciones')
                    ->label('')
                    ->required()
                    ->columnSpanFull()
                    /**
                     * Solo lo que el Word sabe reproducir.
                     *
                     * Las herramientas de mas -imagenes, enlaces a
                     * archivos- producirian un HTML que el generador no
                     * convierte, y se perderia en el documento.
                     */
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'h2', 'h3',
                        'bulletList', 'orderedList',
                        'undo', 'redo',
                    ])
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Ejemplo: El cliente suministra alimentación y hospedaje.'),
            ]);
    }

    /** Lo usa la pestaña "Cálculo de AIU" de la pantalla de edición. */
    public static function aiu(): Section
    {
        return Section::make('AIU: margen sobre el costo')
            ->icon('heroicon-o-calculator')
            ->description('Administración, imprevistos y utilidad. Deciden el precio final.')
            ->columnSpanFull()
            ->collapsed()
            ->schema([
                /**
                 * Los campos viven en la tabla `aius`, no en `cotizaciones`.
                 *
                 * El Group con relationship() hace que Filament lea de esa
                 * fila y la cree al guardar si no existe. Sin el habria que
                 * escribirlo a mano en cada pagina, que es como el sistema
                 * actual acaba teniendo la misma logica en varios sitios.
                 */
                Group::make()
                    ->relationship('aiu')
                    /**
                     * Las banderas llegan de la base como 'SI'/'NO' (y en
                     * algunas filas como '1'/'0'). Hay que convertirlas
                     * AQUI, al cargar: un Toggle que recibe la cadena 'NO'
                     * la toma por verdadera -en PHP toda cadena no vacia lo
                     * es- y al guardar la deja en 'SI', cambiando el precio
                     * de la cotizacion sin que nadie lo pida.
                     */
                    ->mutateRelationshipDataBeforeFillUsing(
                        fn (array $data): array => self::banderasABooleanos($data)
                    )
                    ->columns(3)
                    ->schema([
                        /**
                         * Las banderas se guardan como texto 'SI'/'NO'
                         * porque asi estan los datos de produccion. El
                         * interruptor trabaja con booleanos, de modo que se
                         * traducen en los dos sentidos.
                         *
                         * No se migran a booleano real porque esta tabla la
                         * comparte el sistema que esta en produccion.
                         */
                        Toggle::make('contrato_obra')
                            ->label('Contrato de obra')
                            ->helperText('El IVA grava solo la utilidad, no el total.')
                            ->dehydrateStateUsing(fn ($state): string => $state ? 'SI' : 'NO')
                            ->live(),

                        Toggle::make('mostrar_aiu')
                            ->label('Mostrar AIU desglosado')
                            ->helperText('El cliente ve A, I y U como líneas aparte.')
                            ->dehydrateStateUsing(fn ($state): string => $state ? 'SI' : 'NO')
                            ->live(),

                        Toggle::make('contrato_actual')
                            ->label('Sobre contrato actual')
                            ->dehydrateStateUsing(fn ($state): string => $state ? 'SI' : 'NO'),

                        TextInput::make('administracion')
                            ->label('Administración')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%')
                            ->live(onBlur: true),

                        TextInput::make('imprevistos')
                            ->label('Imprevistos')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%')
                            ->live(onBlur: true),

                        TextInput::make('utilidad')
                            ->label('Utilidad')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%')
                            ->live(onBlur: true),

                        /**
                         * El efecto de los porcentajes, en vivo.
                         *
                         * Sin esto hay que guardar y recalcular para
                         * enterarse de que se tecleo 50 en vez de 5. No es
                         * el precio final: eso depende de los recursos
                         * cargados y se ve en la pantalla de detalle.
                         */
                        Placeholder::make('resumen_aiu')
                            ->label('Efecto sobre el costo directo')
                            ->columnSpanFull()
                            ->content(fn (Get $get): string => self::explicar($get)),
                    ]),
            ]);
    }

    /**
     * Explica en una linea que hacen los porcentajes elegidos.
     *
     * Se usa el mismo objeto de dominio que calcula el precio de verdad, de
     * modo que lo que se lee aqui no puede separarse de lo que se cobra.
     */
    private static function explicar(Get $get): string
    {
        $parametros = new \App\Dominio\Aiu\ParametrosAiu(
            administracion: (float) ($get('administracion') ?? 0),
            imprevistos: (float) ($get('imprevistos') ?? 0),
            utilidad: (float) ($get('utilidad') ?? 0),
            esContratoDeObra: (bool) $get('contrato_obra'),
            muestraAiuDesglosado: (bool) $get('mostrar_aiu'),
        );

        if ($parametros->porcentajeTotal() <= 0) {
            return 'Sin AIU: el precio es el costo directo más IVA.';
        }

        return sprintf(
            'AIU total %s%%: el costo directo se multiplica por %s. %s El AIU %s.',
            rtrim(rtrim(number_format($parametros->porcentajeTotal(), 2, ',', '.'), '0'), ','),
            number_format($parametros->factor(), 4, ',', '.'),
            $parametros->esContratoDeObra
                ? 'El IVA del 19% grava solo la utilidad.'
                : 'El IVA del 19% grava el total con AIU.',
            $parametros->muestraAiuDesglosado
                ? 'aparece desglosado y los ítems muestran su costo directo'
                : 'va incorporado en el precio de cada ítem'
        );
    }

    /**
     * Pasa las banderas de la fila de AIU a booleanos.
     *
     * Las tres columnas guardan texto ('SI'/'NO', y '1'/'0' en algunas
     * filas antiguas). Los interruptores trabajan con booleanos.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function banderasABooleanos(array $datos): array
    {
        foreach (['contrato_obra', 'mostrar_aiu', 'contrato_actual'] as $bandera) {
            $datos[$bandera] = self::esAfirmativo($datos[$bandera] ?? null);
        }

        return $datos;
    }

    /**
     * Interpreta una bandera venga como venga de la base.
     *
     * En esta tabla conviven 'SI'/'NO' y '1'/'0'. Repite a proposito la regla
     * del modelo `Aiu`: aqui llega el valor crudo del formulario, antes de
     * que Eloquent lo haya convertido en modelo.
     */
    private static function esAfirmativo(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        return in_array(
            mb_strtoupper(trim((string) $valor)),
            ['1', 'SI', 'SÍ', 'TRUE'],
            true
        );
    }
}
