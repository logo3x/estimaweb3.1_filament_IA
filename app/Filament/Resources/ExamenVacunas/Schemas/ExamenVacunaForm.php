<?php

namespace App\Filament\Resources\ExamenVacunas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Examenes medicos y vacunas.
 *
 * Codigo y grupo son opcionales: en los datos reales varios registros los
 * traen vacios.
 */
class ExamenVacunaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->label('Codigo')->maxLength(255),
            TextInput::make('grupo')->label('Grupo')->maxLength(255),
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('precio')->label('Precio')->required()->numeric()->minValue(0)->prefix('$'),
        ])->columns(2);
    }
}
