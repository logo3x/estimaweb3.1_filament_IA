<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

/**
 * Oficinas de la cotizacion.
 *
 * Como los recursos simples, mas un costo de movilizacion. Esa columna esta
 * vacia en el cien por cien de los datos reales, asi que es opcional.
 */
class OficinasRelationManager extends RecursoSimpleRelationManager
{
    protected static string $relationship = 'oficinas';

    protected static ?string $title = 'Oficinas';

    protected static function etiquetaSingular(): string
    {
        return 'oficinas';
    }

    /** @return array<int, mixed> */
    protected static function campos(): array
    {
        return [
            ...parent::campos(),
            TextInput::make('movilizacion')
                ->label('Movilización')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->helperText('Opcional: sin uso en los datos actuales.'),
        ];
    }

    /** @return array<int, mixed> */
    protected static function columnas(): array
    {
        return [
            ...parent::columnas(),
            TextColumn::make('movilizacion')
                ->label('Movilización')
                ->money('COP', locale: 'es_CO')
                ->alignEnd()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
