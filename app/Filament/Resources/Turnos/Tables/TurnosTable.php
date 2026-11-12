<?php

namespace App\Filament\Resources\Turnos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Listado de turnos.
 *
 * Se muestran los dias, que es lo que distingue un turno de otro. Los siete
 * factores de recargo quedan ocultos por defecto: interesan al editar, no al
 * buscar.
 */
class TurnosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Turno')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('d_trabajados')
                    ->label('Trabajados')
                    ->numeric(decimalPlaces: 1)
                    ->alignCenter(),

                TextColumn::make('d_descansados')
                    ->label('Descansados')
                    ->numeric(decimalPlaces: 1)
                    ->alignCenter(),

                TextColumn::make('d_trabajadosmes')
                    ->label('Al mes')
                    ->numeric(decimalPlaces: 2)
                    ->alignCenter()
                    ->tooltip('Días trabajados al mes: divide el costo mensual'),

                TextColumn::make('personals_count')
                    ->label('En uso')
                    ->counts('personals')
                    ->alignCenter()
                    ->tooltip('Personas asignadas a este turno'),

                TextColumn::make('hedo')->label('H.E. diurna')->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('heno')->label('H.E. nocturna')->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rn')->label('Recargo nocturno')->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre')
            ->striped();
    }
}
