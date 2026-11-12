<?php

namespace App\Filament\Resources\ExamenVacunas;

use App\Filament\Resources\ExamenVacunas\Schemas\ExamenVacunaForm;
use App\Filament\Resources\ExamenVacunas\Tables\ExamenVacunasTable;
use App\Filament\Resources\ExamenVacunas\Pages\GestionarExamenVacuna;
use App\Models\ExamenVacuna;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExamenVacunaResource extends Resource
{
    protected static ?string $model = ExamenVacuna::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static UnitEnum|string|null $navigationGroup = 'Catálogos';

    protected static ?string $slug = 'examenes-vacunas';

    protected static ?string $navigationLabel = 'Exámenes y vacunas';

    protected static ?string $modelLabel = 'examen o vacuna';

    protected static ?string $pluralModelLabel = 'exámenes y vacunas';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return ExamenVacunaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamenVacunasTable::configure($table);
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
            'index' => GestionarExamenVacuna::route('/'),
        ];
    }
}
