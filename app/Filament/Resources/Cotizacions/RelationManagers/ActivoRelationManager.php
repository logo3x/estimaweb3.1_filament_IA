<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

use App\Dominio\Recursos\CalculadoraAmortizacion;
use App\Dominio\Recursos\InteresConstante;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de los recursos que se amortizan.
 *
 * Herramientas, equipos y consumibles comparten exactamente esta pantalla. En
 * el sistema actual son tres archivos de unas 450 lineas cada uno con la
 * misma estructura de metodos: cualquier mejora hay que hacerla tres veces.
 *
 * Su costo no es el precio de compra, sino lo que cuesta tenerlos
 * disponibles: el precio repartido a lo largo del periodo en que se deprecian,
 * mas reposicion y calibracion.
 */
abstract class ActivoRelationManager extends RelationManager
{
    abstract protected static function etiquetaSingular(): string;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('periodo_evaluacion')
                ->label('Periodo de evaluación')
                ->required()
                ->numeric()
                ->minValue(0.5)
                ->step(0.5)
                ->suffix('años')
                ->live(onBlur: true)
                /**
                 * El periodo decide el interes con que se amortiza. Si no
                 * esta en la tabla, ese interes vale cero y el costo sale
                 * mas bajo sin que nada avise: es el fallo que arrastraba el
                 * sistema actual para TODOS los activos.
                 */
                ->helperText(function ($state): string {
                    if (blank($state)) {
                        return 'De medio en medio año, entre 1 y 25.';
                    }

                    if (! InteresConstante::conoce($state)) {
                        return '⚠ Periodo fuera de la tabla de interés: la amortización saldrá incompleta.';
                    }

                    return 'Interés aplicado: ' . number_format(InteresConstante::para($state) * 100, 2) . '%';
                }),

            TextInput::make('vrepo_anual')
                ->label('Reposición anual')
                ->numeric()
                ->minValue(0)
                ->maxValue(99.99)
                ->suffix('%')
                ->required(fn (): bool => static::exigeValores()),

            TextInput::make('vcalibracion_anual')
                ->label('Calibración anual')
                ->numeric()
                ->minValue(0)
                ->maxValue(99.99)
                ->suffix('%')
                ->required(fn (): bool => static::exigeValores()),
        ])->columns(3);
    }

    /**
     * Si reposicion y calibracion son obligatorias.
     *
     * En consumibles no lo son: en produccion esas columnas vienen vacias.
     */
    protected static function exigeValores(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('items_count')
                    ->label('Elementos')
                    ->counts('items')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'warning')
                    ->tooltip('Elementos del catálogo que lo componen'),

                TextColumn::make('periodo_evaluacion')
                    ->label('Periodo')
                    ->suffix(' años')
                    ->alignCenter()
                    /** Avisa de un periodo que el calculo no reconoce. */
                    ->color(fn ($state): string => InteresConstante::conoce($state) ? 'gray' : 'danger')
                    ->tooltip(fn ($state): ?string => InteresConstante::conoce($state)
                        ? null
                        : 'Periodo fuera de la tabla: la amortización saldrá incompleta'),

                TextColumn::make('vrepo_anual')
                    ->label('Reposición')
                    ->suffix('%')
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('vcalibracion_anual')
                    ->label('Calibración')
                    ->suffix('%')
                    ->alignEnd()
                    ->placeholder('—'),

                /**
                 * Costo mensual calculado con el dominio.
                 *
                 * Es lo que de verdad aporta este activo al precio, y verlo
                 * aqui evita tener que abrir la cotizacion entera para
                 * comprobarlo.
                 */
                TextColumn::make('costo_mensual')
                    ->label('Costo/mes')
                    ->money('COP', locale: 'es_CO')
                    ->alignEnd()
                    ->state(function (Model $record): float {
                        $compra = 0.0;

                        foreach ($record->items as $item) {
                            $compra += (int) $item->cantidad * (float) ($item->catalogo->precio ?? 0);
                        }

                        return (new CalculadoraAmortizacion)->porMes(
                            $compra,
                            $record->periodo_evaluacion,
                            (float) ($record->vrepo_anual ?? 0),
                            (float) ($record->vcalibracion_anual ?? 0),
                        );
                    }),
            ])
            ->headerActions([
                CreateAction::make()->label('Añadir'),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Clonar')
                    ->icon('heroicon-m-document-duplicate')
                    ->beforeReplicaSaved(function ($replica): void {
                        $replica->nombre = $replica->nombre . ' (copia)';
                    })
                    ->successNotificationTitle('Elemento clonado'),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Se borrarán también los elementos del catálogo que lo componen.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre')
            ->striped()
            ->modifyQueryUsing(fn ($query) => $query->with('items.catalogo'))
            ->emptyStateHeading('Sin ' . static::etiquetaSingular());
    }
}
