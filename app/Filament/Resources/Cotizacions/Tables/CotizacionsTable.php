<?php

namespace App\Filament\Resources\Cotizacions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Models\Cotizacion;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listado de cotizaciones.
 *
 * Muestra solo lo que sirve para encontrar una cotizacion y saber en que
 * estado esta. El generador de Filament vuelca las treinta y una columnas de
 * la tabla, lo que produce un listado imposible de leer.
 */
class CotizacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                /**
                 * El numero es como el area comercial identifica una
                 * cotizacion al hablar con el cliente.
                 */
                TextColumn::make('id')
                    ->label('No.')
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Numero copiado')
                    /**
                     * Coincidencia exacta: con el LIKE por defecto, buscar
                     * "55" devuelve tambien 155, 255 y 355.
                     */
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->when(
                        is_numeric($search),
                        fn (Builder $query): Builder => $query->orWhere('id', (int) $search)
                    )),

                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('nombre')
                    ->label('Servicio')
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn ($state): ?string => strlen((string) $state) > 45 ? $state : null)
                    ->toggleable(),

                TextColumn::make('gerencia')
                    ->label('Gerencia')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                /**
                 * El estado del flujo, derivado por el dominio.
                 *
                 * Antes habia dos columnas: 'estado' (vacia en 46 de 48
                 * cotizaciones) y una que repetia a mano las comparaciones
                 * de 'SI'/'NO'. Ahora ambas salen del mismo enum que usa el
                 * resto del sistema.
                 */
                TextColumn::make('estado_aprobacion')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Cotizacion $record): string => $record->estadoAprobacion()->etiquetaCorta())
                    ->color(fn (Cotizacion $record): string => $record->estadoAprobacion()->color())
                    ->toggleable(),

                TextColumn::make('aiu_valortotal_con_iva')
                    ->label('Total')
                    ->money('COP', locale: 'es_CO')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Sin calcular')
                    /**
                     * Es el total guardado, no uno recalculado al vuelo:
                     * recalcular cada fila del listado dispararia el calculo
                     * completo por cotizacion en cada carga de pagina.
                     */
                    ->tooltip('Último total calculado y guardado'),

                TextColumn::make('autor.name')
                    ->label('Creada por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Modificada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gerencia')
                    ->label('Gerencia')
                    ->options(fn (): array => \App\Models\Cotizacion::query()
                        ->whereNotNull('gerencia')
                        ->distinct()
                        ->orderBy('gerencia')
                        ->pluck('gerencia', 'gerencia')
                        ->all()),

                /**
                 * Filtra por el estado del flujo, no por la columna
                 * 'estado': esa esta vacia en casi todas las filas, de modo
                 * que el filtro anterior no devolvia nada.
                 */
                SelectFilter::make('estado_aprobacion')
                    ->label('Estado')
                    ->options(fn (): array => collect(EstadoAprobacion::cases())
                        ->mapWithKeys(fn (EstadoAprobacion $e): array => [$e->value => $e->etiqueta()])
                        ->all())
                    ->query(function ($query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where(
                            fn ($q) => EstadoAprobacion::from($data['value'])->aplicarFiltro($q)
                        );
                    }),
            ])
            ->recordActions([
                /**
                 * Solo consulta por ahora: editar exige el formulario por
                 * pasos, que todavia no existe. Un boton que lleva a un
                 * formulario incompleto es peor que no tenerlo.
                 */
                ViewAction::make(),
            ])
            ->toolbarActions([
                /**
                 * Sin borrado masivo a proposito: una cotizacion aprobada es
                 * un documento comercial, y borrarla arrastra en cascada sus
                 * recursos, items y revisiones.
                 */
                BulkActionGroup::make([]),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->emptyStateHeading('Sin cotizaciones')
            ->emptyStateDescription('Las cotizaciones que se creen apareceran aqui.');
    }
}
