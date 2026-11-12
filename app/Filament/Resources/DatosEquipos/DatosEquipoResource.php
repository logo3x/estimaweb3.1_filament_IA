<?php

namespace App\Filament\Resources\DatosEquipos;

use App\Filament\Resources\CatalogoDePreciosResource;
use App\Filament\Resources\DatosEquipos\Pages\GestionarDatosEquipo;
use App\Models\DatosEquipo;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Catalogo de equipos.
 *
 * Toda la pantalla vive en CatalogoDePreciosResource: aqui solo se declara
 * que modelo gestiona y como llamarlo.
 */
class DatosEquipoResource extends CatalogoDePreciosResource
{
    protected static ?string $model = DatosEquipo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $slug = 'datos-equipos';

    protected static ?string $navigationLabel = 'Equipos';

    protected static ?string $modelLabel = 'equipo';

    protected static ?string $pluralModelLabel = 'equipos';

    protected static ?int $navigationSort = 11;

    public static function etiquetaDelGrupo(): string
    {
        return 'Familia';
    }

    public static function getPages(): array
    {
        return [
            'index' => GestionarDatosEquipo::route('/'),
        ];
    }
}
