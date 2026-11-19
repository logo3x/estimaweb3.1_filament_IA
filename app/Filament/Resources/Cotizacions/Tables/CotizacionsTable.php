<?php

namespace App\Filament\Resources\Cotizacions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Filament\Resources\Cotizacions\CotizacionResource;
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
                    /** En pantallas estrechas basta con el número y el cliente. */
                    ->visibleFrom('xl')
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
                    /** Es la columna más ancha: se recoge antes que las demás. */
                    ->visibleFrom('lg')
                    ->toggleable(),

                TextColumn::make('gerencia')
                    ->label('Gerencia')
                    ->badge()
                    ->sortable()
                    /**
                     * Con el filtro por gerencia en marcha, casi todas las
                     * filas repiten el mismo valor: solo aporta a quien ve
                     * varias gerencias.
                     */
                    ->visibleFrom('lg')
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
                    ->visibleFrom('xl')
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
                    /**
                     * Las opciones salen de la consulta del recurso, no del
                     * modelo crudo: asi solo aparecen las gerencias que ese
                     * usuario alcanza.
                     *
                     * Consultando el modelo directamente, el desplegable
                     * listaba TODAS las gerencias de la empresa a cualquiera
                     * que abriera el listado. No daba acceso a las filas,
                     * pero si revelaba que zonas estan cotizando.
                     */
                    ->options(fn (): array => \App\Filament\Resources\Cotizacions\CotizacionResource::getEloquentQuery()
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
            /**
             * Pulsar una fila lleva a editar, que es a lo que se entra.
             *
             * Por defecto Filament abre la vista de solo lectura y obligaba a
             * buscar el boton de editar. Pero no todas se pueden editar -lo
             * aprobado no se toca, y lo que espera decision solo lo toca
             * quien decide-, asi que esas siguen abriendo la vista: mandarlas
             * a editar daria una pantalla que rebota con una explicacion.
             */
            ->recordUrl(fn (Cotizacion $record): string => $record->puedeSerEditadaPor(auth()->user())
                ? CotizacionResource::getUrl('edit', ['record' => $record])
                : CotizacionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),

                /**
                 * Editar solo aparece cuando el flujo lo permite.
                 *
                 * Lo aprobado no se toca, y lo que espera una decision solo
                 * lo toca quien debe tomarla. Mostrar el boton igualmente
                 * llevaria a una pantalla que rebota con una explicacion:
                 * mejor no ofrecer lo que no se puede hacer.
                 */
                EditAction::make()
                    ->visible(fn (Cotizacion $record): bool => $record->puedeSerEditadaPor(auth()->user())),
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
