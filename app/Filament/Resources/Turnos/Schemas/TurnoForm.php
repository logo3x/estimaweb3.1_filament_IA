<?php

namespace App\Filament\Resources\Turnos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Turnos de trabajo.
 *
 * Cada turno define cuantos dias se trabajan y que recargos genera. Al
 * asignarlo a una persona, estos valores se copian a su fila; desde ahi
 * quedan editables sin alterar el catalogo.
 */
class TurnoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre del turno')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Por ejemplo: 14 X 7 - 12 Horas Día')
                        ->columnSpanFull(),
                ]),

            Section::make('Días')
                ->description('Reparto del mes entre trabajo y descanso')
                ->columns(3)
                ->schema([
                    TextInput::make('d_trabajados')
                        ->label('Días trabajados')
                        ->required()->numeric()->minValue(0),

                    TextInput::make('d_descansados')
                        ->label('Días descansados')
                        ->required()->numeric()->minValue(0),

                    TextInput::make('d_trabajadosmes')
                        ->label('Días trabajados al mes')
                        ->required()->numeric()->minValue(0.001)
                        /**
                         * Divide el costo mensual para obtener el del dia
                         * habil: un cero dejaria ese valor en nada.
                         */
                        ->helperText('Divide el costo mensual: debe ser mayor que cero.'),
                ]),

            Section::make('Horas extra y recargos')
                ->description('Horas que genera el turno en cada condición')
                ->columns(4)
                ->collapsible()
                ->schema([
                    TextInput::make('hedo')->label('Extra diurna')->required()->numeric(),
                    TextInput::make('heno')->label('Extra nocturna')->required()->numeric(),
                    TextInput::make('hedf')->label('Extra festiva diurna')->required()->numeric(),
                    TextInput::make('henf')->label('Extra festiva nocturna')->required()->numeric(),
                    TextInput::make('donfesoca')->label('Dominical/festivo')->required()->numeric(),
                    TextInput::make('donhabitual')->label('Dominical habitual')->required()->numeric(),
                    TextInput::make('rn')->label('Recargo nocturno')->required()->numeric(),
                ]),
        ]);
    }
}
