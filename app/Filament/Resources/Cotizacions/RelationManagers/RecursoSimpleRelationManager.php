<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Base de los recursos que se cobran por cantidad.
 *
 * Aseos, viaticos, servicios y otros son la misma pantalla: un nombre, una
 * unidad y una cantidad que ya trae el valor. En el sistema actual eso son
 * cuatro bloques de codigo repetidos.
 *
 * Vehiculos y oficinas anaden movilizacion, de modo que extienden esta clase
 * en lugar de duplicarla.
 */
abstract class RecursoSimpleRelationManager extends RelationManager
{
    /** Como se llama un elemento de este recurso en singular. */
    abstract protected static function etiquetaSingular(): string;

    public function form(Schema $schema): Schema
    {
        return $schema->components(static::campos());
    }

    /**
     * Campos del formulario.
     *
     * Se declara aparte para que las subclases puedan anadir los suyos sin
     * reescribir los comunes.
     *
     * @return array<int, mixed>
     */
    protected static function campos(): array
    {
        return [
            TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('unidad')
                ->label('Unidad')
                ->required()
                ->maxLength(255)
                ->datalist(['Dia', 'Mes', 'Unidad', 'Global', 'N/A'])
                ->helperText('Etiqueta informativa: no interviene en el cálculo.'),

            /**
             * El valor del recurso viaja en "cantidad".
             *
             * El nombre enganna -- guarda el precio, no un numero de
             * unidades --, pero es asi en produccion y cambiarlo exigiria
             * migrar datos.
             */
            TextInput::make('cantidad')
                ->label('Valor')
                ->required()
                ->numeric()
                ->minValue(0)
                ->prefix('$'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->columns(static::columnas())
            ->headerActions([
                CreateAction::make()->label('Añadir'),
            ])
            ->recordActions([
                EditAction::make(),
                /**
                 * Clonar: muchos recursos se repiten con pequenas variantes,
                 * y volver a escribirlos entero es el principal motivo de
                 * queja sobre el sistema actual.
                 */
                ReplicateAction::make()
                    ->label('Clonar')
                    ->icon('heroicon-m-document-duplicate')
                    ->modalHeading('Clonar elemento')
                    ->beforeReplicaSaved(function ($replica): void {
                        $replica->nombre = $replica->nombre . ' (copia)';
                    })
                    ->successNotificationTitle('Elemento clonado'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre')
            ->striped()
            ->emptyStateHeading('Sin ' . static::etiquetaSingular())
            ->emptyStateDescription('Añada los que correspondan a esta cotización.');
    }

    /**
     * Columnas del listado.
     *
     * @return array<int, mixed>
     */
    protected static function columnas(): array
    {
        return [
            TextColumn::make('nombre')
                ->label('Nombre')
                ->searchable()
                ->sortable()
                ->wrap(),

            TextColumn::make('unidad')
                ->label('Unidad')
                ->badge(),

            TextColumn::make('cantidad')
                ->label('Valor')
                ->money('COP', locale: 'es_CO')
                ->alignEnd()
                ->sortable()
                ->summarize(
                    \Filament\Tables\Columns\Summarizers\Sum::make()
                        ->label('Total')
                        ->money('COP', locale: 'es_CO')
                ),
        ];
    }
}
