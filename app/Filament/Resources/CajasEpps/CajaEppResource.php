<?php

namespace App\Filament\Resources\CajasEpps;

use App\Filament\Resources\CajaPrecargadaResource;
use App\Models\PrecargadoEpp;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Mis cajas de EPP.
 *
 * Toda la pantalla vive en {@see CajaPrecargadaResource}: aqui solo se
 * declara que modelo gestiona y de que catalogo salen los articulos.
 */
class CajaEppResource extends CajaPrecargadaResource
{
    protected static ?string $model = PrecargadoEpp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'caja de EPP';

    protected static ?string $pluralModelLabel = 'cajas de EPP';

    protected static ?string $navigationLabel = 'Cajas de EPP';

    protected static ?string $slug = 'mis-cajas-epp';

    protected static ?int $navigationSort = 4;

    public static function claseDeCatalogo(): string
    {
        return \App\Models\Epp::class;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\GestionarCajaEpp::route('/'),
        ];
    }
}
