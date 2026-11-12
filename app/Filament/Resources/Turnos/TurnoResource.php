<?php

namespace App\Filament\Resources\Turnos;

use App\Filament\Resources\Turnos\Schemas\TurnoForm;
use App\Filament\Resources\Turnos\Tables\TurnosTable;
use App\Filament\Resources\Turnos\Pages\GestionarTurno;
use App\Models\Turno;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TurnoResource extends Resource
{
    protected static ?string $model = Turno::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static UnitEnum|string|null $navigationGroup = 'Catálogos';

    protected static ?string $slug = 'turnos';

    protected static ?string $navigationLabel = 'Turnos';

    protected static ?string $modelLabel = 'turno';

    protected static ?string $pluralModelLabel = 'turnos';

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        return TurnoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TurnosTable::configure($table);
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
            'index' => GestionarTurno::route('/'),
        ];
    }
}
