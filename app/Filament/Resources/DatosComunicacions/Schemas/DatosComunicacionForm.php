<?php

namespace App\Filament\Resources\DatosComunicacions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/** Catalogo de comunicaciones: unico con dos precios, por dia y por mes. */
class DatosComunicacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->label('Codigo')->required()->maxLength(255),
            TextInput::make('grupo')->label('Grupo')->required()->maxLength(255),
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('precio_dia')
                ->label('Precio por dia')
                ->required()->numeric()->minValue(0)->prefix('$'),
            TextInput::make('precio_mes')
                ->label('Precio por mes')
                ->required()->numeric()->minValue(0)->prefix('$')
                ->helperText('La unidad elegida en la cotizacion decide cual se aplica.'),
        ])->columns(2);
    }
}
