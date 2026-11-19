<?php

namespace App\Filament\Resources\Auditorias;

use App\Dominio\Auditoria\AccionAuditada;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\RegistroDeCambio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Quien cambio que, cuando y desde donde.
 *
 * **Solo de consulta, a proposito.** En el sistema anterior
 * `Route::resource('auditoria')` exponia store, update y destroy tras el
 * middleware `auth` sin comprobar rol: cualquiera con sesion podia crear,
 * editar o borrar registros de auditoria. Una auditoria modificable no
 * sirve como evidencia, que es su unico proposito.
 */
class AuditoriaResource extends Resource
{
    protected static ?string $model = RegistroDeCambio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?int $navigationSort = 93;

    protected static ?string $slug = 'auditoria';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $pluralModelLabel = 'registros';

    public static function canAccess(): bool
    {
        return (new Permisos())->puedeElActual(Capacidad::ConsultarLaAuditoria);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cuando')
                    ->label('Cuándo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (RegistroDeCambio $record): string => $record->cuando->diffForHumans()),

                TextColumn::make('accion')
                    ->label('Qué pasó')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AccionAuditada::tryFrom($state)?->etiqueta() ?? $state)
                    ->color(fn (string $state): string => AccionAuditada::tryFrom($state)?->color() ?? 'gray')
                    ->icon(fn (string $state): ?string => AccionAuditada::tryFrom($state)?->icono()),

                TextColumn::make('usuario.name')
                    ->label('Quién')
                    ->searchable()
                    ->placeholder('Sistema')
                    ->description(fn (RegistroDeCambio $record): ?string => $record->ip),

                TextColumn::make('entidad')
                    ->label('Sobre qué')
                    ->badge()
                    ->color('gray')
                    ->description(fn (RegistroDeCambio $record): ?string => $record->cotizacion?->codigo),

                /**
                 * El resumen, o los campos que cambiaron.
                 *
                 * Primero el resumen porque esta escrito para leerse; el
                 * detalle completo se despliega en la fila.
                 */
                TextColumn::make('resumen')
                    ->label('Detalle')
                    ->wrap()
                    ->placeholder(fn (RegistroDeCambio $record): string => $record->cuantosCampos() > 0
                        ? $record->cuantosCampos() . ' campo(s) modificado(s)'
                        : '—')
                    ->tooltip(fn (RegistroDeCambio $record): ?string => static::detalleLegible($record)),
            ])
            ->filters([
                SelectFilter::make('accion')
                    ->label('Qué pasó')
                    ->options(fn (): array => collect(AccionAuditada::cases())
                        ->mapWithKeys(fn (AccionAuditada $a): array => [$a->value => $a->etiqueta()])
                        ->all()),

                SelectFilter::make('usuario_id')
                    ->label('Quién')
                    ->relationship('usuario', 'name')
                    ->searchable()
                    ->preload(),

                /** Lo que toca el dinero o los permisos se mira distinto. */
                Filter::make('solo_delicadas')
                    ->label('Solo lo delicado')
                    ->query(fn (Builder $query): Builder => $query->whereIn('accion', [
                        AccionAuditada::MargenCambiado->value,
                        AccionAuditada::Suplantacion->value,
                        AccionAuditada::Borrado->value,
                    ])),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('cuando', 'desc')
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['usuario:id,name', 'cotizacion:id,codigo']))
            ->emptyStateHeading('Sin registros')
            ->emptyStateDescription('Aquí aparece cada cambio sobre una cotización, su margen y sus aprobaciones.');
    }

    /** Los campos que cambiaron, en texto. */
    private static function detalleLegible(RegistroDeCambio $registro): ?string
    {
        $cambios = $registro->cambios ?? [];

        if ($cambios === []) {
            return null;
        }

        $lineas = [];

        foreach ($cambios as $campo => $valores) {
            if (! is_array($valores) || ! array_key_exists('antes', $valores)) {
                continue;
            }

            $lineas[] = sprintf(
                '%s: %s → %s',
                $campo,
                $valores['antes'] ?? '(vacío)',
                $valores['despues'] ?? '(vacío)',
            );
        }

        return $lineas === [] ? null : implode("\n", $lineas);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditoria::route('/'),
        ];
    }
}
