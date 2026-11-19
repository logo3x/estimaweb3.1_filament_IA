<?php

namespace App\Filament\Resources\Envios;

use App\Dominio\Envios\TipoDeEnvio;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\ImpresionRegistro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Que oferta salio, cuando, quien la genero y a quien se le mando.
 *
 * Es el respaldo ante un cliente que discute una oferta recibida. Por eso
 * la pantalla es **solo de consulta**: no se crea, no se edita y no se
 * borra. En el sistema anterior habia rutas que permitian editar y borrar
 * estos registros, lo que anula su valor como evidencia.
 */
class EnvioResource extends Resource
{
    protected static ?string $model = ImpresionRegistro::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Envíos y documentos';

    protected static ?int $navigationSort = 92;

    protected static ?string $slug = 'envios';

    protected static ?string $modelLabel = 'envío';

    protected static ?string $pluralModelLabel = 'envíos';

    public static function canAccess(): bool
    {
        return (new Permisos())->puedeElActual(Capacidad::ConsultarLaAuditoria);
    }

    /** Nadie crea un envio a mano: se registra solo al generar o enviar. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    /** Una bitacora que se puede borrar no sirve como respaldo. */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
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
                TextColumn::make('fecha_hora')
                    ->label('Cuándo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (ImpresionRegistro $record): string => $record->fecha_hora->diffForHumans()),

                TextColumn::make('tipo')
                    ->label('Qué salió')
                    ->badge()
                    ->formatStateUsing(fn (TipoDeEnvio $state): string => $state->etiqueta())
                    ->color(fn (TipoDeEnvio $state): string => $state->color())
                    ->icon(fn (TipoDeEnvio $state): string => $state->icono()),

                TextColumn::make('cotizacion.codigo')
                    ->label('Cotización')
                    ->searchable()
                    ->sortable()
                    ->url(fn (ImpresionRegistro $record): ?string => $record->cotizacion
                        ? \App\Filament\Resources\Cotizacions\CotizacionResource::getUrl('view', ['record' => $record->cotizacion_id])
                        : null)
                    ->color('primary')
                    ->description(fn (ImpresionRegistro $record): ?string => $record->cotizacion?->cliente),

                TextColumn::make('destinatario')
                    ->label('Contacto')
                    ->searchable()
                    ->wrap()
                    /** La direccion real, cuando fue un correo. */
                    ->description(fn (ImpresionRegistro $record): ?string => $record->correo_destino),

                TextColumn::make('usuario.name')
                    ->label('Quién')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('referencia')
                    ->label('Referencia')
                    ->searchable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                /** Solo aparece cuando algo fallo: si no, estorba. */
                TextColumn::make('fallo')
                    ->label('Problema')
                    ->badge()
                    ->color('danger')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(fn (): array => collect(TipoDeEnvio::cases())
                        ->mapWithKeys(fn (TipoDeEnvio $t): array => [$t->value => $t->etiqueta()])
                        ->all()),

                Filter::make('solo_fallidos')
                    ->label('Solo los que fallaron')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('fallo')),

                Filter::make('solo_al_cliente')
                    ->label('Solo lo enviado al cliente')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('tipo', TipoDeEnvio::CorreoAlCliente->value)
                        ->whereNull('fallo')),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('fecha_hora', 'desc')
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['cotizacion:id,codigo,cliente', 'usuario:id,name']))
            ->emptyStateHeading('Sin envíos registrados')
            ->emptyStateDescription('Aquí aparece cada oferta generada o enviada, con su fecha y su destinatario.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnvios::route('/'),
        ];
    }
}
