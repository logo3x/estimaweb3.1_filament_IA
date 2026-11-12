<?php

namespace App\Filament\Resources\Epps;

use App\Filament\Resources\CatalogoDePreciosResource;
use App\Filament\Resources\Epps\Pages\GestionarEpp;
use App\Models\Epp;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Catalogo de epp.
 *
 * Toda la pantalla vive en CatalogoDePreciosResource: aqui solo se declara
 * que modelo gestiona y como llamarlo.
 */
class EppResource extends CatalogoDePreciosResource
{
    protected static ?string $model = Epp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $slug = 'epp';

    protected static ?string $navigationLabel = 'EPP';

    protected static ?string $modelLabel = 'elemento';

    protected static ?string $pluralModelLabel = 'elementos';

    protected static ?int $navigationSort = 13;

    public static function etiquetaDelGrupo(): string
    {
        return 'Tipo';
    }

    public static function getPages(): array
    {
        return [
            'index' => GestionarEpp::route('/'),
        ];
    }
}
