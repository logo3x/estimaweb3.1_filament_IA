<?php

namespace App\Filament\Resources\DatosConsumibles;

use App\Filament\Resources\CatalogoDePreciosResource;
use App\Filament\Resources\DatosConsumibles\Pages\GestionarDatosConsumible;
use App\Models\DatosConsumible;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Catalogo de consumibles.
 *
 * Toda la pantalla vive en CatalogoDePreciosResource: aqui solo se declara
 * que modelo gestiona y como llamarlo.
 */
class DatosConsumibleResource extends CatalogoDePreciosResource
{
    protected static ?string $model = DatosConsumible::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $slug = 'datos-consumibles';

    protected static ?string $navigationLabel = 'Consumibles';

    protected static ?string $modelLabel = 'consumible';

    protected static ?string $pluralModelLabel = 'consumibles';

    protected static ?int $navigationSort = 12;

    public static function etiquetaDelGrupo(): string
    {
        return 'Familia';
    }

    public static function getPages(): array
    {
        return [
            'index' => GestionarDatosConsumible::route('/'),
        ];
    }
}
