<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

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

/**
 * Los items de la oferta: las lineas que ve el cliente.
 *
 * Cada recurso se reparte entre ellos, y de ese reparto sale el precio
 * unitario del documento final. Sin items no hay a que asignar los recursos,
 * de modo que es lo primero que se carga tras los datos de cabecera.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Ítems';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-list-bullet';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('numero')
                ->label('N.º')
                ->required()
                ->numeric()
                ->minValue(1)
                /**
                 * El numero NO es unico: en los datos reales varios items
                 * comparten el mismo (la cotizacion 14 tiene cuatro con el
                 * numero 1). Sirve para agrupar, no para identificar.
                 */
                ->helperText('Puede repetirse para agrupar ítems relacionados.'),

            TextInput::make('cantidad')
                ->label('Cantidad')
                ->required()
                ->numeric()
                ->minValue(1)
                ->helperText('Multiplica el costo de los recursos asignados.'),

            TextInput::make('unidad')
                ->label('Unidad')
                ->required()
                ->maxLength(255)
                ->datalist(['Und', 'Servicio', 'Global', 'Mes', 'Día']),

            TextInput::make('nombre')
                ->label('Descripción')
                ->required()
                ->maxLength(255)
                ->columnSpanFull()
                ->helperText('Tal como debe aparecer en la oferta.'),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->columns([
                TextColumn::make('numero')
                    ->label('N.º')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('nombre')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('unidad')
                    ->label('Unidad')
                    ->badge(),

                TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                /**
                 * Cuantos recursos tiene asignados.
                 *
                 * Un item en cero no aporta nada al precio, y es facil que
                 * pase inadvertido hasta que el total sale mas bajo de lo
                 * esperado.
                 */
                TextColumn::make('revisiones_count')
                    ->label('Recursos')
                    ->counts('revisiones')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'warning')
                    ->tooltip('Recursos asignados a este ítem'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir ítem')
                    /** El numero siguiente, para no tener que mirarlo. */
                    ->mutateDataUsing(function (array $data): array {
                        $data['numero'] = $data['numero']
                            ?? ($this->getOwnerRecord()->items()->max('numero') ?? 0) + 1;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Clonar')
                    ->icon('heroicon-m-document-duplicate')
                    ->beforeReplicaSaved(function ($replica): void {
                        $replica->nombre = $replica->nombre . ' (copia)';
                    })
                    ->successNotificationTitle('Ítem clonado'),
                /**
                 * Borrar un item arrastra sus revisiones en cascada: el
                 * reparto de recursos que tuviera se pierde.
                 */
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Se perderá también el reparto de recursos de este ítem.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('numero')
            ->striped()
            ->emptyStateHeading('Sin ítems')
            ->emptyStateDescription('Los ítems son las líneas que verá el cliente. Añada al menos uno antes de cargar recursos.');
    }
}
