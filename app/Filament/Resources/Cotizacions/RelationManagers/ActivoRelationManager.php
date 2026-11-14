<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

use App\Dominio\Recursos\CalculadoraAmortizacion;
use App\Dominio\Recursos\InteresConstante;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    /** La caja precargada de este tipo de recurso. */
    abstract protected static function claseDeCaja(): string;

    /** El catalogo del que salen los elementos y sus precios. */
    abstract protected static function claseDeCatalogo(): string;

    /** La clase que guarda cada linea del grupo. */
    abstract protected static function claseDeElemento(): string;

    /** Columna por la que cada linea apunta a su grupo. */
    abstract protected static function columnaDelGrupo(): string;

    /** Columna por la que cada linea apunta al catalogo. */
    abstract protected static function columnaDelCatalogo(): string;

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
                static::accionElementos(),
                static::accionCargarCaja(),
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

    /**
     * Los elementos del catalogo que componen el grupo.
     *
     * Sin esto un grupo se crea vacio y se queda vacio: la columna
     * "Elementos" cuenta cero y el costo sale cero, sin que nada explique
     * por que.
     */
    protected static function accionElementos(): Action
    {
        return Action::make('elementos')
            ->label('Elementos')
            ->icon('heroicon-m-list-bullet')
            ->color('gray')
            ->modalHeading(fn (Model $record): string => 'Elementos de ' . $record->nombre)
            ->modalSubmitActionLabel('Guardar')
            ->modalWidth('3xl')
            ->fillForm(fn (Model $record): array => [
                'elementos' => $record->items->map(fn (Model $item): array => [
                    'catalogo' => $item->{static::columnaDelCatalogo()},
                    'cantidad' => $item->cantidad,
                ])->all(),
            ])
            ->schema([
                Repeater::make('elementos')
                    ->label('')
                    ->addActionLabel('Añadir elemento')
                    ->columns(4)
                    ->defaultItems(0)
                    ->schema([
                        Select::make('catalogo')
                            ->label('Del catálogo')
                            ->options(fn (): array => static::opcionesDelCatalogo())
                            ->searchable()
                            ->required()
                            ->columnSpan(2),

                        TextInput::make('cantidad')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        /** El precio del catalogo, para ver el peso de cada linea. */
                        Placeholder::make('precio')
                            ->label('Precio unitario')
                            ->content(function (Get $get): string {
                                $id = $get('catalogo');

                                if (blank($id)) {
                                    return '—';
                                }

                                $precio = (static::claseDeCatalogo())::find($id)?->precio ?? 0;

                                return '$ ' . number_format((float) $precio, 2, ',', '.');
                            }),
                    ]),
            ])
            ->action(function (array $data, Model $record): void {
                static::guardarElementos($record, $data['elementos'] ?? []);

                Notification::make()
                    ->success()
                    ->title('Elementos guardados')
                    ->body('Recalcule los totales para ver el efecto en el precio.')
                    ->send();
            });
    }

    /**
     * Vuelca una caja precargada dentro del grupo.
     *
     * Las cajas son listas guardadas con nombre -"Caja de Soldador"- que
     * pertenecen a quien las creo. Evitan tener que buscar los mismos
     * quince elementos del catalogo en cada cotizacion.
     *
     * Se añaden a lo que ya hubiera, no lo reemplazan: es habitual cargar
     * una caja y completarla a mano.
     */
    protected static function accionCargarCaja(): Action
    {
        return Action::make('cargarCaja')
            ->label('Cargar caja')
            ->icon('heroicon-m-archive-box-arrow-down')
            ->color('gray')
            ->modalHeading('Cargar una caja precargada')
            ->modalDescription('Sus elementos se añaden a los que ya tenga el grupo.')
            ->modalSubmitActionLabel('Cargar')
            ->modalWidth('lg')
            /** Sin cajas propias el boton no sirve de nada. */
            ->visible(fn (): bool => (static::claseDeCaja())::query()->de(auth()->user())->exists())
            ->schema([
                Select::make('caja')
                    ->label('Caja')
                    ->options(fn (): array => (static::claseDeCaja())::query()
                        ->de(auth()->user())
                        ->withCount('elementos')
                        ->orderBy('nombre')
                        ->get()
                        ->mapWithKeys(fn (Model $caja): array => [
                            $caja->id => $caja->nombre . ' (' . $caja->elementos_count . ' elementos)',
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->helperText('Solo aparecen las cajas que usted creó.'),
            ])
            ->action(function (array $data, Model $record): void {
                $caja = (static::claseDeCaja())::query()->de(auth()->user())->find($data['caja']);

                if (! $caja) {
                    Notification::make()
                        ->danger()
                        ->title('Esa caja ya no está disponible')
                        ->send();

                    return;
                }

                $cuantos = static::volcarCaja($record, $caja);

                if ($cuantos === 0) {
                    Notification::make()
                        ->warning()
                        ->title('La caja está vacía')
                        ->body('Ninguno de sus elementos apunta a un artículo del catálogo.')
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("Se añadieron {$cuantos} elemento(s)")
                    ->body('Recalcule los totales para ver el efecto en el precio.')
                    ->send();
            });
    }

    /**
     * Reemplaza los elementos del grupo por los del formulario.
     *
     * Se borra y se vuelve a escribir en una transaccion: las lineas no
     * tienen identidad propia -son una cantidad y una referencia al
     * catalogo-, asi que casar cada fila con la anterior no aporta nada.
     *
     * @param  array<int, array{catalogo: mixed, cantidad: mixed}>  $elementos
     */
    protected static function guardarElementos(Model $record, array $elementos): void
    {
        DB::transaction(function () use ($record, $elementos): void {
            $record->items()->delete();

            foreach ($elementos as $elemento) {
                if (blank($elemento['catalogo'] ?? null)) {
                    continue;
                }

                $record->items()->create([
                    static::columnaDelCatalogo() => $elemento['catalogo'],
                    'cantidad' => max(1, (int) ($elemento['cantidad'] ?? 1)),
                    'unidad' => 'N/A',
                ]);
            }
        });
    }

    /**
     * Añade al grupo los elementos de una caja.
     *
     * Si un elemento ya esta en el grupo se le suma la cantidad en vez de
     * repetir la linea. Las cajas reales traen ids repetidos -hay una con
     * el mismo articulo tres veces-, y duplicar lineas haria que el mismo
     * articulo apareciera varias veces en la oferta.
     *
     * @return int Cuantos elementos se añadieron o sumaron.
     */
    protected static function volcarCaja(Model $record, Model $caja): int
    {
        $ids = $caja->idsDelCatalogo();

        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($record, $ids): int {
            $columna = static::columnaDelCatalogo();

            /** Lo que el grupo ya tiene, para sumar en vez de duplicar. */
            $existentes = $record->items()->get()->keyBy($columna);

            foreach (array_count_values($ids) as $idCatalogo => $cuantas) {
                $linea = $existentes->get($idCatalogo);

                if ($linea) {
                    $linea->increment('cantidad', $cuantas);

                    continue;
                }

                $existentes->put($idCatalogo, $record->items()->create([
                    $columna => $idCatalogo,
                    'cantidad' => $cuantas,
                    'unidad' => 'N/A',
                ]));
            }

            return count($ids);
        });
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
}
