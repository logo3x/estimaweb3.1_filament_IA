<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

use App\Models\DatosComunicacion;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

/**
 * Servicios de comunicaciones de la cotizacion.
 *
 * Como los recursos simples, pero tomando el precio de su catalogo: la unidad
 * elegida decide si se cobra por dia o por mes.
 */
class ComunicacionesRelationManager extends RecursoSimpleRelationManager
{
    protected static string $relationship = 'comunicaciones';

    protected static ?string $title = 'Comunicaciones';

    protected static function etiquetaSingular(): string
    {
        return 'servicios de comunicaciones';
    }

    /** @return array<int, mixed> */
    protected static function campos(): array
    {
        return [
            /**
             * Al elegir del catalogo se copian nombre y precio. El precio se
             * copia y no se consulta en vivo a proposito: una cotizacion ya
             * enviada no puede cambiar de importe porque el catalogo suba.
             */
            Select::make('id_datos_comunicaciones')
                ->label('Del catálogo')
                ->options(fn (): array => DatosComunicacion::query()
                    ->orderBy('nombre')
                    ->pluck('nombre', 'id')
                    ->all())
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                    $catalogo = DatosComunicacion::query()->find($state);

                    if ($catalogo === null) {
                        return;
                    }

                    $set('nombre', $catalogo->nombre);
                    $set('cantidad', $get('unidad') === 'Mes'
                        ? $catalogo->precio_mes
                        : $catalogo->precio_dia);
                })
                ->columnSpanFull(),

            TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('unidad')
                ->label('Unidad')
                ->required()
                ->options([
                    'Dia' => 'Día',
                    'Mes' => 'Mes',
                ])
                ->default('Dia')
                ->live()
                /** Cambiar la unidad vuelve a tomar el precio que toca. */
                ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                    $catalogo = DatosComunicacion::query()->find($get('id_datos_comunicaciones'));

                    if ($catalogo === null) {
                        return;
                    }

                    $set('cantidad', $state === 'Mes' ? $catalogo->precio_mes : $catalogo->precio_dia);
                }),

            TextInput::make('cantidad')
                ->label('Valor')
                ->required()
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->helperText('Se copia del catálogo y queda editable.'),
        ];
    }

    /** @return array<int, mixed> */
    protected static function columnas(): array
    {
        return [
            TextColumn::make('nombre')
                ->label('Servicio')
                ->searchable()
                ->sortable()
                ->wrap(),

            TextColumn::make('unidad')
                ->label('Unidad')
                ->badge(),

            TextColumn::make('catalogo.codigo')
                ->label('Código')
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('cantidad')
                ->label('Valor')
                ->money('COP', locale: 'es_CO')
                ->alignEnd()
                ->sortable()
                ->summarize(
                    \Filament\Tables\Columns\Summarizers\Sum::make()
                        ->label('Total')
                        ->money('COP', locale: 'es_CO')
                ),
        ];
    }
}
