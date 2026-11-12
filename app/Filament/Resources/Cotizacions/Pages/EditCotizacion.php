<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Dominio\CalcularCotizacion;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * Edicion de una cotizacion.
 *
 * Los datos de cabecera arriba y los recursos en pestanas debajo, una por
 * categoria. Las pestanas son Relation Managers de Filament, de modo que cada
 * una sabe a que cotizacion pertenece sin necesidad de guardar el id en la
 * sesion, que es como lo resuelve el sistema actual.
 */
class EditCotizacion extends EditRecord
{
    protected static string $resource = CotizacionResource::class;

    public function getTitle(): string
    {
        return 'Cotización ' . $this->getRecord()->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            /**
             * Recalcular y guardar los totales.
             *
             * La pantalla de consulta siempre recalcula en vivo, pero el
             * listado lee las columnas guardadas: esta accion las pone al
             * dia despues de cambiar recursos.
             */
            Action::make('recalcular')
                ->label('Recalcular totales')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->action(function (): void {
                    $resultado = CalcularCotizacion::crear()->para($this->getRecord());

                    $this->getRecord()->update([
                        'valor_total' => $resultado->totales->costoDirecto,
                        'valor_total_con_aiu' => $resultado->totales->subtotal,
                        'aiu_administracion' => $resultado->totales->administracion,
                        'aiu_imprevistos' => $resultado->totales->imprevistos,
                        'aiu_utilidad' => $resultado->totales->utilidad,
                        'aiu_iva' => $resultado->totales->iva,
                        'aiu_valortotal_con_iva' => $resultado->totales->total,
                    ]);

                    $aviso = Notification::make()
                        ->success()
                        ->title('Totales actualizados')
                        ->body('Total: $' . number_format($resultado->totales->total, 2, ',', '.'));

                    if ($resultado->tieneAvisos()) {
                        $aviso->warning()
                            ->title('Totales actualizados, con avisos')
                            ->body(count($resultado->avisos) . ' recurso(s) no se pudieron calcular. Revise la pantalla de consulta.');
                    }

                    $aviso->send();
                }),

            ViewAction::make()->label('Ver detalle'),

            DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('Se borrarán también todos sus ítems, recursos y asignaciones.'),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Datos guardados';
    }

    /**
     * Las pestanas se mantienen visibles mientras se edita.
     *
     * Por defecto Filament las pone debajo del formulario; aqui conviene que
     * sean lo primero tras guardar los datos, porque es donde esta el grueso
     * del trabajo.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos de la cotización';
    }
}
