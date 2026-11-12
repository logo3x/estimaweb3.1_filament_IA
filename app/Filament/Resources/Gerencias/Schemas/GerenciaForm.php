<?php

namespace App\Filament\Resources\Gerencias\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/** Gerencias de la empresa: agrupan usuarios y cotizaciones. */
class GerenciaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(255)->columnSpanFull(),
        ]);
    }
}
