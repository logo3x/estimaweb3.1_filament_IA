<?php

namespace App\Filament\Resources\Cotizacions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
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

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Aprobada' => 'success',
                        'Rechazada' => 'danger',
                        'En elaboración' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('Sin estado')
                    ->sortable(),

                /**
                 * Estado del flujo de aprobacion en una sola columna.
                 *
                 * Son dos niveles, tecnico y financiero, y lo que interesa de
                 * un vistazo es si falta alguno.
                 */
                TextColumn::make('aprobacion1')
                    ->label('Aprobaciones')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record): string => match (true) {
                        $record->aprobacion1 === 'SI' && $record->aprobacion2 === 'SI' => 'Completa',
                        $record->aprobacion1 === 'SI' => 'Falta financiera',
                        $record->aprobacion2 === 'SI' => 'Falta técnica',
                        default => 'Pendiente',
                    })
                    ->color(fn ($state, $record): string => $record->aprobacion1 === 'SI' && $record->aprobacion2 === 'SI'
                        ? 'success'
                        : 'warning')
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

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'En elaboración' => 'En elaboración',
                        'Aprobada' => 'Aprobada',
                        'Rechazada' => 'Rechazada',
                    ]),
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
