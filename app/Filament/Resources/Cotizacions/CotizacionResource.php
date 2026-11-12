<?php

namespace App\Filament\Resources\Cotizacions;

use App\Filament\Resources\Cotizacions\Pages\ListCotizacions;
use App\Filament\Resources\Cotizacions\Pages\ViewCotizacion;
use App\Filament\Resources\Cotizacions\Schemas\CotizacionForm;
use App\Filament\Resources\Cotizacions\Tables\CotizacionsTable;
use App\Models\Cotizacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Las cotizaciones: el centro del sistema.
 *
 * De momento solo listado y consulta. La edicion completa llega con el
 * formulario por pasos, que es donde vive la complejidad: once categorias de
 * recurso repartidas entre los items de la oferta.
 */
class CotizacionResource extends Resource
{
    protected static ?string $model = Cotizacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'cotización';

    protected static ?string $pluralModelLabel = 'cotizaciones';

    protected static ?string $navigationLabel = 'Cotizaciones';

    /** La ruta en castellano: Filament deduciria "cotizacions". */
    protected static ?string $slug = 'cotizaciones';

    protected static ?int $navigationSort = 1;

    /** Lo que la busqueda global encuentra al escribir en la barra superior. */
    protected static ?string $recordTitleAttribute = 'cliente';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['cliente', 'codigo', 'nombre'];
    }

    /** @return array<string, string|null> */
    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'No.' => $record->id,
            'Servicio' => mb_strimwidth((string) $record->nombre, 0, 60, '...'),
        ];
    }

    /** Cuantas cotizaciones hay, visible junto al menu. */
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return CotizacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CotizacionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        /**
         * Sin 'create' ni 'edit' todavia.
         *
         * Crear o editar una cotizacion exige el formulario por pasos con las
         * once categorias de recurso y su reparto entre los items. Hasta que
         * exista, dejar esas rutas activas ofreceria un formulario que
         * guardaria cotizaciones incompletas.
         */
        return [
            'index' => ListCotizacions::route('/'),
            'view' => ViewCotizacion::route('/{record}'),
        ];
    }
}
