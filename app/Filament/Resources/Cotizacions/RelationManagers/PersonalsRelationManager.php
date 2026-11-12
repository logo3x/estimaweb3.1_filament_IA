<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

use App\Dominio\Nomina\CalculadoraNomina;
use App\Dominio\Nomina\DatosPersona;
use App\Dominio\Nomina\FactoresLegales;
use App\Models\Personal;
use App\Models\Turno;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * El personal de la cotizacion.
 *
 * Es el recurso mas complejo y el que mas peso tiene en el precio. Ademas del
 * salario lleva auxilios, capacitaciones, horas extra y recargos, y de ahi
 * salen las prestaciones.
 *
 * Al elegir un turno, sus factores se copian a la fila: desde ahi quedan
 * editables sin alterar el catalogo, porque una persona puede salirse del
 * patron de su turno.
 */
class PersonalsRelationManager extends RelationManager
{
    protected static string $relationship = 'personals';

    protected static ?string $title = 'Personal';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(3)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Cargo o nombre')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    Select::make('rol')
                        ->label('Rol')
                        ->required()
                        ->options([
                            'Rol Mensual' => 'Mensual',
                            'Rol Diario' => 'Diario',
                        ])
                        ->default('Rol Mensual')
                        ->live()
                        ->helperText('Decide si el salario se entiende por mes o por día.'),

                    Select::make('id_turno')
                        ->label('Turno')
                        ->options(fn (): array => Turno::query()->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->searchable()
                        ->required()
                        ->live()
                        /**
                         * Al elegir turno se copian sus factores. Sin turno
                         * no se puede calcular el costo por dia habil.
                         */
                        ->afterStateUpdated(function ($state, callable $set): void {
                            $turno = Turno::query()->find($state);

                            if ($turno === null) {
                                return;
                            }

                            foreach (['hedo', 'heno', 'hedf', 'henf', 'donfesoca', 'donhabitual', 'rn'] as $factor) {
                                $set($factor, $turno->{$factor});
                            }
                        })
                        ->helperText('Sus factores se copian abajo y quedan editables.')
                        ->columnSpan(2),

                    Toggle::make('f_prestacional')
                        ->label('Cotiza prestaciones')
                        /**
                         * Se guarda como 0/1. El sistema actual lo compara
                         * con 'SI', de modo que esa rama nunca se ejecuta y
                         * quien cotiza se liquida con el factor equivocado.
                         */
                        ->helperText('Cambia el factor prestacional aplicado.'),

                    Toggle::make('aux_educacion')
                        ->label('Auxilio de educación')
                        ->helperText('Suma el auxilio vigente al costo mensual.'),
                ]),

            Section::make('Remuneración')
                ->columns(4)
                ->schema([
                    TextInput::make('salario')
                        ->label(fn (callable $get): string => $get('rol') === 'Rol Diario'
                            ? 'Salario diario'
                            : 'Salario mensual')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$'),

                    TextInput::make('auxiliosin')
                        ->label('Auxilio sin incidencia')
                        ->required()->numeric()->minValue(0)->prefix('$')
                        ->default(0),

                    TextInput::make('capacitaciones')
                        ->label('Capacitaciones')
                        ->required()->numeric()->minValue(0)->prefix('$')
                        ->default(0),

                    TextInput::make('p_he')
                        ->label('% horas extra')
                        ->required()->numeric()->minValue(0)->suffix('%')
                        ->default(0),
                ]),

            Section::make('Horas y recargos')
                ->description('Heredados del turno, editables para esta persona')
                ->columns(4)
                ->collapsible()
                ->schema([
                    TextInput::make('hedo')->label('Extra diurna')->required()->numeric()->default(0),
                    TextInput::make('heno')->label('Extra nocturna')->required()->numeric()->default(0),
                    TextInput::make('hedf')->label('Extra festiva diurna')->required()->numeric()->default(0),
                    TextInput::make('henf')->label('Extra festiva nocturna')->required()->numeric()->default(0),
                    TextInput::make('donfesoca')->label('Dominical/festivo')->required()->numeric()->default(0),
                    TextInput::make('donhabitual')->label('Dominical habitual')->required()->numeric()->default(0),
                    TextInput::make('rn')->label('Recargo nocturno')->required()->numeric()->default(0),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->columns([
                TextColumn::make('nombre')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('rol')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('Rol ', '', $state)),

                TextColumn::make('turno.nombre')
                    ->label('Turno')
                    ->badge()
                    ->placeholder('Sin turno'),

                TextColumn::make('salario')
                    ->label('Salario')
                    ->money('COP', locale: 'es_CO')
                    ->alignEnd()
                    ->sortable(),

                /**
                 * Costo real calculado con el dominio.
                 *
                 * El salario no es lo que cuesta la persona: faltan
                 * prestaciones, auxilios, dotacion y recargos, que pueden
                 * mas que duplicarlo.
                 */
                TextColumn::make('costo_mes')
                    ->label('Costo/mes')
                    ->money('COP', locale: 'es_CO')
                    ->alignEnd()
                    ->state(fn (Personal $record): float => static::costoDe($record)?->porMes ?? 0.0)
                    ->tooltip('Salario más prestaciones, auxilios y dotación')
                    ->weight('bold'),

                TextColumn::make('costo_dia_habil')
                    ->label('Costo/día hábil')
                    ->money('COP', locale: 'es_CO')
                    ->alignEnd()
                    ->state(fn (Personal $record): float => static::costoDe($record)?->porDiaHabil ?? 0.0)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()->label('Añadir persona'),
            ])
            ->recordActions([
                EditAction::make(),
                /**
                 * Clonar es lo que mas se usa aqui: una cuadrilla son varias
                 * personas con el mismo perfil y pequenas diferencias.
                 */
                ReplicateAction::make()
                    ->label('Clonar')
                    ->icon('heroicon-m-document-duplicate')
                    ->beforeReplicaSaved(function ($replica): void {
                        $replica->nombre = $replica->nombre . ' (copia)';
                    })
                    ->successNotificationTitle('Persona clonada'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre')
            ->striped()
            ->modifyQueryUsing(fn ($query) => $query->with([
                'turno', 'epps.catalogo', 'examenesVacunas.catalogo',
                'auxiliosAdicionales', 'auxiliosConvencionales',
            ]))
            ->emptyStateHeading('Sin personal')
            ->emptyStateDescription('El personal suele ser el mayor peso del precio.');
    }

    /**
     * Costo de una persona, o null si falta algun dato para calcularlo.
     *
     * Sin turno no hay dias trabajados al mes, y sin factores legales no hay
     * nomina: en ambos casos se muestra cero en vez de reventar la tabla.
     */
    private static function costoDe(Personal $persona): ?object
    {
        if ($persona->turno === null) {
            return null;
        }

        try {
            return (new CalculadoraNomina(FactoresLegales::vigentes()))
                ->calcular(DatosPersona::desdeModelo($persona));
        } catch (\Throwable) {
            return null;
        }
    }
}
