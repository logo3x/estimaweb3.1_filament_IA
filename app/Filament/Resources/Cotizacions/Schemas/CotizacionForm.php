<?php

namespace App\Filament\Resources\Cotizacions\Schemas;

use App\Models\Gerencia;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Datos de cabecera de una cotizacion.
 *
 * Es el primer paso: hasta que estos datos no esten guardados no se pueden
 * cargar recursos, porque todos cuelgan de la cotizacion.
 */
class CotizacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cliente')
                ->columns(3)
                ->columnSpanFull()
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
                ]),

            Section::make('Servicio')
                ->columns(3)
                ->columnSpanFull()
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
                ]),

            Section::make('Condiciones comerciales')
                ->columns(3)
                ->columnSpanFull()
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
                ]),
        ]);
    }
}
