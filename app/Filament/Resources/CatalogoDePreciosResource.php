<?php

namespace App\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Base de los catalogos de precios.
 *
 * Herramientas, equipos, consumibles y EPP son la misma pantalla: un codigo,
 * un nombre, un grupo y un precio. En el sistema actual eso son cuatro
 * archivos casi identicos, de modo que cualquier mejora -- una columna nueva,
 * un filtro, una validacion -- hay que hacerla cuatro veces y es facil que
 * una se quede atras.
 *
 * Aqui cada catalogo solo declara su modelo y sus etiquetas. La pantalla se
 * escribe una vez.
 */
abstract class CatalogoDePreciosResource extends Resource
{
    protected static \UnitEnum|string|null $navigationGroup = 'Catálogos';

    /** Nombre del grupo tal como lo entiende el area tecnica. */
    abstract public static function etiquetaDelGrupo(): string;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')
                ->label('Código')
                ->required()
                ->maxLength(255)
                ->helperText('Referencia interna del elemento.'),

            TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            /**
             * El grupo se escribe libremente, con sugerencias de los ya
             * usados.
             *
             * Un Select cerrado rechazaria cualquier grupo nuevo, y estos
             * catalogos crecen: obligar a tocar el codigo para anadir una
             * familia no tiene sentido.
             */
            TextInput::make('grupo')
                ->label(fn (): string => static::etiquetaDelGrupo())
                ->required()
                ->maxLength(255)
                ->datalist(fn (): array => static::getModel()::query()
                    ->whereNotNull('grupo')
                    ->distinct()
                    ->orderBy('grupo')
                    ->limit(100)
                    ->pluck('grupo')
                    ->all())
                ->helperText('Escriba uno existente o uno nuevo.'),

            TextInput::make('precio')
                ->label('Precio')
                ->required()
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->helperText('Precio unitario de referencia.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(70),

                TextColumn::make('grupo')
                    ->label(fn (): string => static::etiquetaDelGrupo())
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('precio')
                    ->label('Precio')
                    ->money('COP', locale: 'es_CO')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('grupo')
                    ->label(fn (): string => static::etiquetaDelGrupo())
                    ->options(fn (): array => static::getModel()::query()
                        ->whereNotNull('grupo')
                        ->distinct()
                        ->orderBy('grupo')
                        ->pluck('grupo', 'grupo')
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre')
            ->striped()
            ->persistFiltersInSession()
            /**
             * Los catalogos tienen miles de filas: cargar veinticinco por
             * pagina evita arrastrar la tabla entera a memoria.
             */
            ->paginated([25, 50, 100]);
    }
}
