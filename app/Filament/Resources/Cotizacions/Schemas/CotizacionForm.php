<?php

namespace App\Filament\Resources\Cotizacions\Schemas;

use App\Models\Gerencia;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
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
            self::cliente(),
            self::servicio(),
            self::condiciones(),
            /**
             * El AIU va el ultimo, como en el sistema actual.
             *
             * Es lo que se cuadra al cerrar la cotizacion, cuando los
             * recursos ya estan cargados; arranca plegado porque no se
             * toca en cada edicion.
             */
            self::aiu(),
        ]);
    }

    /** A quien se le cotiza. */
    private static function cliente(): Section
    {
        return Section::make('Cliente')
            ->icon('heroicon-o-building-office-2')
            ->columnSpanFull()
            ->columns(3)
            ->schema([
                TextInput::make('cliente')
                    ->label('Cliente')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),

                TextInput::make('codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Referencia de la oferta.'),

                TextInput::make('contacto')
                    ->label('Contacto')
                    ->maxLength(255),

                TextInput::make('cargo')
                    ->label('Cargo del contacto')
                    ->required()
                    ->maxLength(255),

                TextInput::make('referencia')
                    ->label('Referencia del cliente')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    /** Que se cotiza. */
    private static function servicio(): Section
    {
        return Section::make('Servicio')
            ->icon('heroicon-o-wrench-screwdriver')
            ->columnSpanFull()
            ->columns(3)
            ->schema([
                TextInput::make('nombre')
                    ->label('Nombre del servicio')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

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
                    ->validationMessages([
                        'min' => 'Al menos 1 día: reparte el costo de EPP y exámenes.',
                    ]),

                DatePicker::make('estimado_inicio')
                    ->label('Inicio estimado')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                /**
                 * La gerencia es texto libre, no una lista cerrada.
                 *
                 * En los datos reales esta columna guarda NUMEROS (4, 6,
                 * 7, 8) que no corresponden al catalogo actual, donde
                 * solo hay "Zona 1" a "Zona 5". Son ids de una version
                 * anterior de esa tabla. Un Select cerrado rechazaria
                 * todas las cotizaciones existentes al editarlas.
                 *
                 * Queda pendiente decidir si se normalizan esos valores.
                 */
                TextInput::make('gerencia')
                    ->label('Gerencia')
                    ->maxLength(255)
                    ->datalist(fn (): array => Gerencia::query()
                        ->orderBy('nombre')
                        ->pluck('nombre')
                        ->all()),

                Textarea::make('alcance')
                    ->label('Alcance')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),

                Textarea::make('observaciones')
                    ->label('Observaciones')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    /** En que condiciones. */
    private static function condiciones(): Section
    {
        return Section::make('Condiciones comerciales')
            ->icon('heroicon-o-document-check')
            ->columnSpanFull()
            ->columns(3)
            ->schema([
                TextInput::make('vigencia')
                    ->label('Vigencia')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Por ejemplo: 30 días.'),

                TextInput::make('forma_pago')
                    ->label('Forma de pago')
                    ->required()
                    ->maxLength(255),

                TextInput::make('otras_condiciones')
                    ->label('Otras condiciones')
                    ->maxLength(255),
            ]);
    }

    /**
     * Con que margen: Administracion, Imprevistos y Utilidad.
     *
     * Es lo ultimo porque es lo que convierte el costo en precio, y porque
     * las dos banderas solo tienen sentido cuando ya se sabe que se esta
     * cotizando.
     *
     * Los campos cuelgan de la relacion `aiu`, una fila aparte: por eso van
     * con prefijo. Filament crea o actualiza esa fila al guardar.
     */
    private static function aiu(): Section
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
