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

    /**
     * Cada quien ve las cotizaciones de su gerencia.
     *
     * Se sobrescribe aqui y no en la tabla porque este metodo es el que usa
     * TODO el recurso: el listado, la busqueda global, el contador del menu
     * y la resolucion de la ruta al abrir una por su direccion. Puesto solo
     * en la tabla, bastaria escribir la URL para saltarselo.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return (new \App\Dominio\Permisos\AlcanceDeCotizaciones())
            ->aplicar(parent::getEloquentQuery(), auth()->user());
    }

    /** Cuantas cotizaciones hay, visible junto al menu. */
    public static function getNavigationBadge(): ?string
    {
        /**
         * Sobre la consulta del recurso, no sobre el modelo: asi cuenta lo
         * que ese usuario puede ver. Contar el modelo directamente anunciaba
         * cuarenta y ocho a quien solo alcanzaba catorce.
         */
        return (string) static::getEloquentQuery()->count();
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
         * El orden es el del sistema actual, que es el que conoce quien
         * cotiza: primero los items, luego el personal -que suele ser el
         * mayor peso del precio-, despues los activos que se amortizan, y
         * al final la asignacion.
         *
         * Las siete categorias menores van agrupadas en una sola pestaña,
         * igual que alli: sueltas ocupaban la mitad de la barra siendo lo
         * que menos se toca.
         */
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\PersonalsRelationManager::class,
            RelationManagers\HerramientasRelationManager::class,
            RelationManagers\EquiposRelationManager::class,
            RelationManagers\ConsumiblesRelationManager::class,
            RelationManagers\CategoriasAdicionales::grupo(),
            /**
             * La asignacion va al final: reparte entre los items lo que las
             * pestañas anteriores cargaron. Un recurso que no pase por aqui
             * no cuenta en el precio.
             */
            RelationManagers\RevisionesRelationManager::class,
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
