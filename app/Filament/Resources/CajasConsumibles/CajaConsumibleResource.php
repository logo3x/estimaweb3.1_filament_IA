<?php

namespace App\Filament\Resources\CajasConsumibles;

use App\Filament\Resources\CajaPrecargadaResource;
use App\Models\PrecargadoConsumible;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Mis cajas de consumibles.
 *
 * Toda la pantalla vive en {@see CajaPrecargadaResource}: aqui solo se
 * declara que modelo gestiona y de que catalogo salen los articulos.
 */
class CajaConsumibleResource extends CajaPrecargadaResource
{
    protected static ?string $model = PrecargadoConsumible::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $modelLabel = 'caja de consumibles';

    protected static ?string $pluralModelLabel = 'cajas de consumibles';

    protected static ?string $navigationLabel = 'Cajas de consumibles';

    protected static ?string $slug = 'mis-cajas-consumibles';

    protected static ?int $navigationSort = 3;

    public static function claseDeCatalogo(): string
    {
        return \App\Models\DatosConsumible::class;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\GestionarCajaConsumible::route('/'),
        ];
    }
}
