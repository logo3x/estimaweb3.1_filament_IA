<?php

namespace App\Filament\Resources\Gerencias;

use App\Filament\Resources\Gerencias\Schemas\GerenciaForm;
use App\Filament\Resources\Gerencias\Tables\GerenciasTable;
use App\Filament\Resources\Gerencias\Pages\GestionarGerencia;
use App\Models\Gerencia;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GerenciaResource extends Resource
{
    protected static ?string $model = Gerencia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static UnitEnum|string|null $navigationGroup = 'Catálogos';

    protected static ?string $slug = 'gerencias';

    protected static ?string $navigationLabel = 'Gerencias';

    protected static ?string $modelLabel = 'gerencia';

    protected static ?string $pluralModelLabel = 'gerencias';

    protected static ?int $navigationSort = 17;

    public static function form(Schema $schema): Schema
    {
        return GerenciaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GerenciasTable::configure($table);
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
            'index' => GestionarGerencia::route('/'),
        ];
    }
}
