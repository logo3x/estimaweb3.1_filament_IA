<?php

namespace App\Filament\Resources\ExamenVacunas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamenVacunasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Codigo')->searchable()->sortable()->placeholder('-'),
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable()->wrap(),
                TextColumn::make('grupo')->label('Grupo')->badge()->searchable()->placeholder('-'),
                TextColumn::make('precio')->label('Precio')->money('COP', locale: 'es_CO')->sortable()->alignEnd(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('nombre')
            ->striped();
    }
}
