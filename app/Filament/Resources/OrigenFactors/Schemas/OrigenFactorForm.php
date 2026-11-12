<?php

namespace App\Filament\Resources\OrigenFactors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Factores legales de nomina.
 *
 * Es una sola fila que el area administrativa actualiza cuando cambia la
 * normativa, tipicamente cada enero con el salario minimo. De aqui sale el
 * costo de TODO el personal de TODAS las cotizaciones: un valor mal escrito
 * desplaza el precio de la empresa entera.
 */
class OrigenFactorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Valores de ley')
                ->description('Se actualizan cuando cambia la normativa, normalmente en enero')
                ->columns(3)
                ->schema([
                    TextInput::make('Salario_min_vigente')
                        ->label('Salario mínimo')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->prefix('$')
                        /**
                         * Decide tres cosas: si el contrato es de salario
                         * integral (13 minimos), que factor prestacional
                         * aplica (10 minimos) y si hay auxilio de transporte
                         * (2 minimos).
                         */
                        ->helperText('Define los umbrales de salario integral y auxilio de transporte.'),

                    TextInput::make('aux_trans')
                        ->label('Auxilio de transporte')
                        ->required()->numeric()->minValue(0)->prefix('$')
                        ->helperText('Solo hasta 2 salarios mínimos.'),

                    TextInput::make('aux_edu')
                        ->label('Auxilio de educación')
                        ->required()->numeric()->minValue(0)->prefix('$'),

                    TextInput::make('Pro_horas_trabajadasdia')
                        ->label('Horas trabajadas al día')
                        ->required()
                        ->numeric()
                        ->minValue(0.001)
                        ->step(0.00001)
                        /** Divide el salario diario para obtener el valor hora. */
                        ->helperText('Divide el salario diario: debe ser mayor que cero.'),
                ]),

            Section::make('Recargos')
                ->description('Multiplicadores de ley sobre el valor de la hora')
                ->columns(4)
                ->schema([
                    TextInput::make('HEDO')->label('Extra diurna')->required()->numeric()->step(0.001),
                    TextInput::make('HENO')->label('Extra nocturna')->required()->numeric()->step(0.001),
                    TextInput::make('HEDF')->label('Extra festiva diurna')->required()->numeric()->step(0.001),
                    TextInput::make('HENF')->label('Extra festiva nocturna')->required()->numeric()->step(0.001),
                    TextInput::make('DYFO')->label('Dominical/festivo')->required()->numeric()->step(0.001),
                    TextInput::make('DH')->label('Dominical habitual')->required()->numeric()->step(0.001),
                    TextInput::make('RN')->label('Recargo nocturno')->required()->numeric()->step(0.001),
                    TextInput::make('DIAF')
                        ->label('Día de la familia')
                        ->required()->numeric()->step(0.00001)
                        ->suffix('%'),
                ]),

            Section::make('Factores prestacionales')
                ->description('Se elige uno según el salario y si la persona cotiza prestaciones')
                ->columns(3)
                ->schema([
                    TextInput::make('FPMI')
                        ->label('Salario integral')
                        ->required()->numeric()->step(0.0001)
                        ->helperText('Por encima de 13 salarios mínimos.'),

                    TextInput::make('FEME10')
                        ->label('Con prestaciones, bajo 10 SMLV')
                        ->required()->numeric()->step(0.0001),

                    TextInput::make('FEMA10')
                        ->label('Con prestaciones, sobre 10 SMLV')
                        ->required()->numeric()->step(0.0001),

                    TextInput::make('FNME10')
                        ->label('Sin prestaciones, bajo 10 SMLV')
                        ->required()->numeric()->step(0.0001),

                    TextInput::make('FNMA10')
                        ->label('Sin prestaciones, sobre 10 SMLV')
                        ->required()->numeric()->step(0.0001),
                ]),

            Section::make('Topes sugeridos del AIU')
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('min_administracion')->label('Administración mínima')->numeric()->suffix('%'),
                    TextInput::make('min_imprevistos')->label('Imprevistos mínimos')->numeric()->suffix('%'),
                    TextInput::make('min_utilidad')->label('Utilidad mínima')->numeric()->suffix('%'),
                ]),
        ]);
    }
}
