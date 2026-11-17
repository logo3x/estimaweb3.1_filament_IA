<?php

namespace App\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Base de las cajas precargadas.
 *
 * Una caja es una lista de articulos del catalogo guardada con nombre -"Caja
 * de Soldador"- que pertenece a quien la creo. Al cotizar se vuelca entera
 * en un grupo, en vez de buscar los mismos quince articulos cada vez.
 *
 * Herramientas, equipos, consumibles y EPP tienen cada uno su tabla, pero
 * son la misma pantalla: un nombre y una lista de articulos. Por eso las
 * cuatro clases concretas solo declaran sus modelos y sus etiquetas.
 *
 * Cada quien gestiona **solo las suyas**: son listas de trabajo personales.
 */
abstract class CajaPrecargadaResource extends Resource
{
    protected static \UnitEnum|string|null $navigationGroup = 'Mis cajas';

    /** El catalogo del que salen los articulos. */
    abstract public static function claseDeCatalogo(): string;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre de la caja')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->helperText('Como la reconocerá al cotizar. Por ejemplo: "Caja de soldador".'),

                    /**
                     * Los articulos, por la relacion de elementos.
                     *
                     * Un multiple sobre la relacion y no un repetidor: una
                     * caja es solo una lista, la cantidad se decide al
                     * volcarla en la cotizacion.
                     */
                    Select::make('articulos')
                        ->label('Artículos del catálogo')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => static::opcionesDelCatalogo())
                        ->columnSpanFull()
                        ->helperText('Busque por nombre. Puede añadir los que quiera.')
                        /**
                         * Al abrir, los articulos salen de la tabla de
                         * elementos, no de una columna.
                         */
                        ->afterStateHydrated(function (Select $componente, ?Model $record): void {
                            $componente->state($record?->idsDelCatalogo() ?? []);
                        }),
                ]),
        ]);
    }

    /**
     * El catalogo como lista para elegir.
     *
     * Con el precio al lado: son miles de articulos con nombres parecidos y
     * el precio es lo que distingue uno de otro.
     *
     * @return array<int, string>
     */
    protected static function opcionesDelCatalogo(): array
    {
        return (static::claseDeCatalogo())::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'precio'])
            ->mapWithKeys(fn (Model $fila): array => [
                $fila->id => $fila->nombre . ' — $' . number_format((float) $fila->precio, 0, ',', '.'),
            ])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Caja')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('elementos_count')
                    ->label('Artículos')
                    ->counts('elementos')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'warning')
                    ->tooltip('Una caja vacía no aporta nada al cotizar'),

                /** Lo que costaria comprar todo lo que lleva, una unidad de cada. */
                TextColumn::make('valor')
                    ->label('Valor de referencia')
                    ->money('COP', locale: 'es_CO')
                    ->alignEnd()
                    ->state(fn (Model $record): float => static::valorDeLaCaja($record))
                    ->tooltip('Suma del catálogo, una unidad de cada artículo'),

                TextColumn::make('updated_at')
                    ->label('Modificada')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            /**
             * Sin boton de crear en la tabla.
             *
             * El de la cabecera de la pagina -GestionarCajas- es el bueno:
             * escribe los articulos en su tabla aparte. Uno aqui saldria
             * duplicado en pantalla y ademas crearia cajas vacias.
             */
            ->recordActions([
                EditAction::make()
                    ->modalWidth('3xl')
                    /**
                     * Los articulos viven en otra tabla, asi que se
                     * escriben a mano despues de guardar la caja.
                     */
                    ->using(function (Model $record, array $data): Model {
                        /**
                         * En transaccion: se borran los articulos antes de
                         * escribir los nuevos. Si fallara a mitad -una
                         * desconexion, un tiempo agotado- la caja quedaria
                         * con el nombre nuevo y la lista vacia.
                         */
                        return DB::transaction(function () use ($record, $data): Model {
                            $record->update(['nombre' => $data['nombre']]);

                            $columna = $record::columnaDelCatalogo();
                            $record->elementos()->delete();

                            foreach (array_filter($data['articulos'] ?? []) as $idCatalogo) {
                                $record->elementos()->create([$columna => $idCatalogo]);
                            }

                            return $record;
                        });
                    })
                    ->successNotificationTitle('Caja guardada'),
                ReplicateAction::make()
                    ->label('Duplicar')
                    ->icon('heroicon-m-document-duplicate')
                    /**
                     * El contador de la tabla no es una columna: viene de
                     * withCount y al copiarlo Eloquent intenta escribirlo.
                     */
                    ->excludeAttributes(['elementos_count'])
                    ->beforeReplicaSaved(function (Model $replica): void {
                        $replica->nombre = $replica->nombre . ' (copia)';
                        $replica->id_user = auth()->id();
                    })
                    /** Duplicar una caja sin su contenido no sirve de nada. */
                    ->after(function (Model $replica, Model $record): void {
                        /** En transaccion: una copia a medias no sirve de nada. */
                        DB::transaction(function () use ($replica, $record): void {
                            foreach ($record->elementos as $elemento) {
                                $replica->elementos()->create(
                                    $elemento->only([$record::columnaDelCatalogo()])
                                );
                            }
                        });
                    })
                    ->successNotificationTitle('Caja duplicada'),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Las cotizaciones que ya la usaron no se ven afectadas.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre')
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('elementos'))
            ->emptyStateHeading('Todavía no tiene cajas')
            ->emptyStateDescription('Una caja guarda los artículos que suele usar juntos, para cargarlos de una vez al cotizar.');
    }

    /** Lo que vale todo lo que lleva, una unidad de cada articulo. */
    protected static function valorDeLaCaja(Model $caja): float
    {
        $ids = $caja->idsDelCatalogo();

        if ($ids === []) {
            return 0.0;
        }

        return (float) (static::claseDeCatalogo())::query()
            ->whereIn('id', $ids)
            ->sum('precio');
    }

    /**
     * Cada quien ve y toca solo sus cajas.
     *
     * Va en la consulta del recurso y no solo en la tabla: asi tambien
     * protege al abrir una por su direccion.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->de(auth()->user());
    }

    /** Cuantas cajas propias hay, junto al menu. */
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }
}
