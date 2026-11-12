<?php

namespace App\Filament\Resources\DatosHerramientas;

use App\Filament\Resources\CatalogoDePreciosResource;
use App\Filament\Resources\DatosHerramientas\Pages\GestionarDatosHerramienta;
use App\Models\DatosHerramienta;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Catalogo de herramientas.
 *
 * Toda la pantalla vive en CatalogoDePreciosResource: aqui solo se declara
 * que modelo gestiona y como llamarlo.
 */
class DatosHerramientaResource extends CatalogoDePreciosResource
{
    protected static ?string $model = DatosHerramienta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $slug = 'datos-herramientas';

    protected static ?string $navigationLabel = 'Herramientas';

    protected static ?string $modelLabel = 'herramienta';

    protected static ?string $pluralModelLabel = 'herramientas';

    protected static ?int $navigationSort = 10;

    public static function etiquetaDelGrupo(): string
    {
        return 'Familia';
    }

    public static function getPages(): array
    {
        return [
            'index' => GestionarDatosHerramienta::route('/'),
        ];
    }
}
