<?php

namespace App\Filament\Resources\DatosComunicacions;

use App\Filament\Resources\DatosComunicacions\Schemas\DatosComunicacionForm;
use App\Filament\Resources\DatosComunicacions\Tables\DatosComunicacionsTable;
use App\Filament\Resources\DatosComunicacions\Pages\GestionarDatosComunicacion;
use App\Models\DatosComunicacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DatosComunicacionResource extends Resource
{
    protected static ?string $model = DatosComunicacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static UnitEnum|string|null $navigationGroup = 'Catálogos';

    protected static ?string $slug = 'datos-comunicaciones';

    protected static ?string $navigationLabel = 'Comunicaciones';

    protected static ?string $modelLabel = 'servicio de comunicaciones';

    protected static ?string $pluralModelLabel = 'servicios de comunicaciones';

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return DatosComunicacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatosComunicacionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => GestionarDatosComunicacion::route('/'),
        ];
    }
}
