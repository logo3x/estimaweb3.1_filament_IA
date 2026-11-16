<?php

namespace App\Filament\Resources\CajasEquipos;

use App\Filament\Resources\CajaPrecargadaResource;
use App\Models\PrecargadoEquipo;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Mis cajas de equipos.
 *
 * Toda la pantalla vive en {@see CajaPrecargadaResource}: aqui solo se
 * declara que modelo gestiona y de que catalogo salen los articulos.
 */
class CajaEquipoResource extends CajaPrecargadaResource
{
    protected static ?string $model = PrecargadoEquipo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog8Tooth;

    protected static ?string $modelLabel = 'caja de equipos';

    protected static ?string $pluralModelLabel = 'cajas de equipos';

    protected static ?string $navigationLabel = 'Cajas de equipos';

    protected static ?string $slug = 'mis-cajas-equipos';

    protected static ?int $navigationSort = 2;

    public static function claseDeCatalogo(): string
    {
        return \App\Models\DatosEquipo::class;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\GestionarCajaEquipo::route('/'),
        ];
    }
}
