<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

use App\Dominio\Aiu\UnidadDeCobro;
use App\Models\Revision;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Reparte los recursos entre los items de la oferta.
 *
 * Es la pieza que convierte recursos cargados en precio: el motor recorre
 * estas filas, y un recurso que no este aqui no cuenta, por bien cargado que
 * este. La cotizacion 7 tiene quince recursos y vale cero por esto mismo.
 *
 * Cada fila dice: "de este recurso, esta cantidad corresponde a este item".
 */
class RevisionesRelationManager extends RelationManager
{
    protected static string $relationship = 'revisiones';

    protected static ?string $title = 'Asignación';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-arrows-right-left';

    /**
     * Unidades que el calculo acepta para cada tipo de recurso.
     *
     * Solo personal, herramientas y equipos cobran por tiempo; para el resto
     * la unidad es una etiqueta y "N/A" es lo correcto.
     *
     * Ofrecer SOLO lo valido es lo que impide repetir el fallo del sistema
     * actual, donde una unidad no prevista hacia valer cero al recurso sin
     * aviso alguno.
     *
     * @var array<string, list<string>>
     */
    private const UNIDADES_POR_TIPO = [
        'personal' => ['Dia Habil', 'Dia Calendario', 'Mes'],
        'herramienta' => ['Dia Habil', 'Mes'],
        'equipo' => ['Dia Habil', 'Mes'],
    ];

    /** Unidad para los recursos que se cobran por cantidad. */
    private const UNIDAD_POR_CANTIDAD = 'N/A';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('id_item_cotizacion')
                ->label('Ítem de la oferta')
                ->required()
                ->options(fn (): array => $this->getOwnerRecord()
                    ->items()
                    ->orderBy('numero')
                    ->get()
                    ->mapWithKeys(fn ($item): array => [
                        $item->id => $item->numero . '. ' . mb_strimwidth($item->nombre, 0, 60, '…'),
                    ])
                    ->all())
                ->searchable()
                ->columnSpanFull()
                ->helperText('A qué línea de la oferta se imputa este costo.'),

            Select::make('tipo_recurso')
                ->label('Tipo de recurso')
                ->required()
                ->options(fn (): array => $this->tiposDisponibles())
                ->live()
                ->afterStateUpdated(function (callable $set): void {
                    $set('recurso_id', null);
                    $set('unidad', null);
                })
                /**
                 * Se deshidrata a proposito: no es una columna de la tabla,
                 * pero using() lo necesita para saber a que columna id_*
                 * guardar el recurso. Se descarta en guardar().
                 */
                ->formatStateUsing(fn (?Revision $record): ?string => $record?->tipoDeRecurso()),

            Select::make('recurso_id')
                ->label('Recurso')
                ->required()
                ->options(fn (callable $get): array => $this->recursosDe($get('tipo_recurso')))
                ->searchable()
                /** Tambien se deshidrata: lo consume guardar(). */
                ->formatStateUsing(function (?Revision $record) {
                    $columna = $record?->columnaDelRecurso();

                    return $columna === null ? null : $record->{$columna};
                })
                ->helperText('Debe estar cargado en su pestaña antes de poder asignarlo.'),

            /**
             * Las unidades dependen del tipo: para los recursos que cobran
             * por cantidad solo cabe "N/A", y ofrecer otra cosa llevaria a
             * un recurso que vale cero.
             */
            Select::make('unidad')
                ->label('Unidad')
                ->required()
                ->options(fn (callable $get): array => $this->unidadesDe($get('tipo_recurso')))
                ->helperText(fn (callable $get): string => array_key_exists((string) $get('tipo_recurso'), self::UNIDADES_POR_TIPO)
                    ? 'Decide el precio: no cuesta lo mismo un día que un mes.'
                    : 'Este recurso se cobra por cantidad: la unidad es informativa.'),

            TextInput::make('cantidad')
                ->label('Cantidad')
                ->required()
                ->numeric()
                ->minValue(0.00000001)
                ->step(0.00000001)
                ->default(1)
                ->helperText('Cuántas unidades del recurso van a este ítem.'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.numero')
                    ->label('Ítem')
                    ->sortable()
                    ->alignCenter()
                    ->badge(),

                TextColumn::make('item.nombre')
                    ->label('Línea de la oferta')
                    ->limit(40)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->state(fn (Revision $record): string => ucfirst((string) $record->tipoDeRecurso())),

                TextColumn::make('recurso')
                    ->label('Recurso')
                    ->state(fn (Revision $record): string => (string) ($record->recurso()->nombre ?? '—'))
                    ->wrap()
                    ->searchable(false),

                TextColumn::make('unidad')
                    ->label('Unidad')
                    ->badge()
                    /**
                     * Avisa de una unidad con la que no se puede calcular.
                     *
                     * Solo aplica a los recursos que cobran por tiempo: en
                     * los demas "N/A" es correcto.
                     */
                    ->color(fn (Revision $record): string => $this->unidadEsValida($record) ? 'gray' : 'danger')
                    ->tooltip(fn (Revision $record): ?string => $this->unidadEsValida($record)
                        ? null
                        : 'El cálculo no reconoce esta unidad: el recurso aportará cero'),

                TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('id_item_cotizacion')
                    ->label('Ítem')
                    ->options(fn (): array => $this->getOwnerRecord()
                        ->items()
                        ->orderBy('numero')
                        ->pluck('nombre', 'id')
                        ->all()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar recurso')
                    ->using(fn (array $data): Model => $this->guardar($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (Revision $record, array $data): Model => $this->guardar($data, $record)),

                /**
                 * Clonar: el mismo recurso suele repartirse entre varios
                 * items con cantidades distintas.
                 */
                ReplicateAction::make()
                    ->label('Clonar')
                    ->icon('heroicon-m-document-duplicate')
                    ->successNotificationTitle('Asignación clonada'),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id_item_cotizacion')
            ->striped()
            ->modifyQueryUsing(fn ($query) => $query->with('item'))
            ->emptyStateHeading('Sin asignaciones')
            ->emptyStateDescription('Los recursos solo cuentan en el precio cuando se asignan a un ítem.');
    }

    /**
     * Guarda la asignacion resolviendo a que columna va el recurso.
     *
     * Revision::asignarRecurso() limpia las otras diez columnas, de modo que
     * nunca queda una fila apuntando a dos recursos a la vez.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardar(array $data, ?Revision $revision = null): Model
    {
        $tipo = (string) ($data['tipo_recurso'] ?? '');
        $recursoId = $data['recurso_id'] ?? null;

        $revision ??= new Revision;

        /**
         * Solo las columnas reales: tipo_recurso y recurso_id son campos del
         * formulario, no de la tabla, y sirven para decidir en cual de las
         * once columnas id_* va el recurso.
         */
        $revision->fill([
            'id_cotizacion' => $this->getOwnerRecord()->getKey(),
            'id_item_cotizacion' => $data['id_item_cotizacion'],
            'unidad' => $data['unidad'],
            'cantidad' => $data['cantidad'],
        ]);

        $modelo = $this->modeloDe($tipo);

        if ($modelo !== null && $recursoId !== null) {
            $recurso = $modelo::query()->find($recursoId);

            if ($recurso !== null) {
                $revision->asignarRecurso($recurso);
            }
        }

        $revision->save();

        return $revision;
    }

    /**
     * Tipos de recurso que esta cotizacion tiene cargados.
     *
     * No se ofrecen los vacios: elegir "herramienta" sin herramientas
     * cargadas lleva a un desplegable sin opciones.
     *
     * @return array<string, string>
     */
    private function tiposDisponibles(): array
    {
        $cotizacion = $this->getOwnerRecord();

        $tipos = [];

        foreach (Revision::RECURSOS as $columna => $modelo) {
            $tipo = substr($columna, 3);

            if ($modelo::query()->where('id_cotizacion', $cotizacion->getKey())->exists()) {
                $tipos[$tipo] = ucfirst($tipo);
            }
        }

        return $tipos;
    }

    /**
     * Recursos de ese tipo cargados en esta cotizacion.
     *
     * @return array<int, string>
     */
    private function recursosDe(?string $tipo): array
    {
        $modelo = $this->modeloDe((string) $tipo);

        if ($modelo === null) {
            return [];
        }

        return $modelo::query()
            ->where('id_cotizacion', $this->getOwnerRecord()->getKey())
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    /**
     * Unidades aceptables para ese tipo.
     *
     * @return array<string, string>
     */
    private function unidadesDe(?string $tipo): array
    {
        $validas = self::UNIDADES_POR_TIPO[(string) $tipo] ?? [self::UNIDAD_POR_CANTIDAD];

        $opciones = [];

        foreach ($validas as $unidad) {
            $opciones[$unidad] = UnidadDeCobro::desdeTexto($unidad)?->etiqueta() ?? $unidad;
        }

        return $opciones;
    }

    /** Si la unidad guardada permite calcular el precio de esa revision. */
    private function unidadEsValida(Revision $revision): bool
    {
        $tipo = (string) $revision->tipoDeRecurso();

        if (! array_key_exists($tipo, self::UNIDADES_POR_TIPO)) {
            return true;
        }

        return in_array((string) $revision->unidad, self::UNIDADES_POR_TIPO[$tipo], true);
    }

    /** @return class-string<Model>|null */
    private function modeloDe(string $tipo): ?string
    {
        return Revision::RECURSOS['id_' . $tipo] ?? null;
    }
}
