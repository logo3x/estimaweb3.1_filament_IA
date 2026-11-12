<?php

namespace App\Filament\Resources\Cotizacions;

use App\Filament\Resources\Cotizacions\Pages\CreateCotizacion;
use App\Filament\Resources\Cotizacions\Pages\EditCotizacion;
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
        /**
         * El orden importa: primero los items, porque sin ellos no hay a
         * que asignar los recursos; luego el personal, que suele ser el mayor
         * peso del precio.
         */
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\PersonalsRelationManager::class,
            RelationManagers\HerramientasRelationManager::class,
            RelationManagers\EquiposRelationManager::class,
            RelationManagers\ConsumiblesRelationManager::class,
            RelationManagers\VehiculosRelationManager::class,
            RelationManagers\OficinasRelationManager::class,
            RelationManagers\AseosRelationManager::class,
            RelationManagers\ViaticosRelationManager::class,
            RelationManagers\ServiciosRelationManager::class,
            RelationManagers\ComunicacionesRelationManager::class,
            RelationManagers\OtrosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        /**
         * La creacion solo pide los datos de cabecera; los recursos se
         * cargan en la pantalla de edicion, donde cada pestana sabe a que
         * cotizacion pertenece.
         */
        return [
            'index' => ListCotizacions::route('/'),
            'create' => CreateCotizacion::route('/create'),
            'view' => ViewCotizacion::route('/{record}'),
            'edit' => EditCotizacion::route('/{record}/edit'),
        ];
    }
}
