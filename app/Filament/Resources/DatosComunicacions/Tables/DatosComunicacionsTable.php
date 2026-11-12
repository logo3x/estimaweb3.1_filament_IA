<?php

namespace App\Filament\Resources\DatosComunicacions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DatosComunicacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Codigo')->searchable()->sortable(),
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable()->wrap(),
                TextColumn::make('grupo')->label('Grupo')->badge()->searchable(),
                TextColumn::make('precio_dia')->label('Por dia')->money('COP', locale: 'es_CO')->sortable()->alignEnd(),
                TextColumn::make('precio_mes')->label('Por mes')->money('COP', locale: 'es_CO')->sortable()->alignEnd(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('nombre')
            ->striped();
    }
}
