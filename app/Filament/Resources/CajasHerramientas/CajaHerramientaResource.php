<?php

namespace App\Filament\Resources\CajasHerramientas;

use App\Filament\Resources\CajaPrecargadaResource;
use App\Models\PrecargadoHerramienta;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Mis cajas de herramientas.
 *
 * Toda la pantalla vive en {@see CajaPrecargadaResource}: aqui solo se
 * declara que modelo gestiona y de que catalogo salen los articulos.
 */
class CajaHerramientaResource extends CajaPrecargadaResource
{
    protected static ?string $model = PrecargadoHerramienta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'caja de herramientas';

    protected static ?string $pluralModelLabel = 'cajas de herramientas';

    protected static ?string $navigationLabel = 'Cajas de herramientas';

    protected static ?string $slug = 'mis-cajas-herramientas';

    protected static ?int $navigationSort = 1;

    public static function claseDeCatalogo(): string
    {
        return \App\Models\DatosHerramienta::class;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\GestionarCajaHerramienta::route('/'),
        ];
    }
}
