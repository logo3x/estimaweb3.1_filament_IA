<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Dominio\CalcularCotizacion;
use App\Dominio\Documentos\GeneradorDeOferta;
use App\Filament\Resources\Cotizacions\AccionesDeAprobacion;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

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

    /**
     * Los botones de la cabecera, por orden de importancia.
     *
     * Antes eran seis en fila, tres de ellos en amarillo compitiendo por la
     * atencion y "Borrar" en rojo pegado a la accion principal, facil de
     * pulsar por error.
     *
     * Ahora queda a la vista solo lo que se usa a diario: la accion del
     * flujo que toque en este momento -enviar, aprobar, rechazar- y
     * descargar la oferta. El resto se recoge en un menu.
     */
    protected function getHeaderActions(): array
    {
        return [
            ...AccionesDeAprobacion::todas(),

            $this->descargarOferta(),

            ActionGroup::make([
                $this->recalcular(),
                ViewAction::make()->label('Ver detalle')->icon('heroicon-o-eye'),

                /** Separado del resto: borrar arrastra items y recursos. */
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Borrar cotización')
                        ->requiresConfirmation()
                        ->modalDescription('Se borrarán también todos sus ítems, recursos y asignaciones.')
                        /** Lo que ya se aprobo no se borra. */
                        ->visible(fn (): bool => $this->getRecord()->puedeSerEditadaPor(auth()->user())),
                ])->dropdown(false),
            ])
                ->label('Más acciones')
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }

    /**
     * Recalcular y guardar los totales.
     *
     * La pantalla de consulta siempre recalcula en vivo, pero el listado
     * lee las columnas guardadas: esta accion las pone al dia despues de
     * cambiar recursos.
     */
    private function recalcular(): Action
    {
        return Action::make('recalcular')
            ->label('Recalcular totales')
            ->icon('heroicon-o-calculator')
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
            });
    }

    /**
     * Descarga la oferta en Word.
     *
     * El documento se genera al vuelo con los totales del dominio, no con
     * las columnas guardadas: asi nunca se envia al cliente un precio
     * desactualizado.
     */
    private function descargarOferta(): Action
    {
        return Action::make('descargarOferta')
            ->label('Descargar oferta')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->outlined()
            ->action(function () {
                try {
                    $archivo = GeneradorDeOferta::crear()->generar($this->getRecord());
                } catch (\Throwable $e) {
                    /**
                     * `Throwable` y no `RuntimeException`: PhpWord lanza
                     * excepciones que extienden `\Exception` a secas, asi
                     * que una plantilla corrupta se escapaba de este catch
                     * y daba pantalla de error.
                     */
                    Log::error('No se pudo generar la oferta', [
                        'cotizacion' => $this->getRecord()->id,
                        'error' => $e->getMessage(),
                        'tipo' => $e::class,
                    ]);

                    Notification::make()
                        ->danger()
                        ->title('No se pudo generar la oferta')
                        ->body($e instanceof \RuntimeException
                            ? $e->getMessage()
                            : 'Hubo un problema al armar el archivo. Avise a sistemas: el detalle quedó registrado.')
                        ->persistent()
                        ->send();

                    return null;
                }

                return response()->download($archivo)->deleteFileAfterSend();
            });
    }

    /**
     * No se entra a editar lo que el flujo tiene bloqueado.
     *
     * Se sustituye la autorizacion de Filament, que respondería un 403
     * seco. Aqui interesa lo contrario: llevar al detalle y explicar por
     * que no se puede -esta en aprobacion, ya se aprobo-, que es
     * informacion util y no un error del usuario.
     *
     * Va en authorizeAccess() y no en mount() porque Filament lo llama
     * tambien en cada peticion de Livewire: puesto solo en mount(), una
     * peticion posterior volveria a dar el 403.
     */
    protected function authorizeAccess(): void
    {
        $cotizacion = $this->getRecord();

        /**
         * El alcance por gerencia se comprueba aparte y con un 404.
         *
         * Al sustituir authorizeAccess() se deja de pasar por la policy, que
         * es donde vive esa regla. Hoy coincide con lo que decide el flujo
         * de aprobacion, pero por casualidad: si manana se relaja una rama
         * de esas reglas, la edicion dejaria de comprobar la gerencia sin
         * que nadie lo note.
         *
         * Va antes que la redireccion explicativa a proposito: un aviso de
         * "esta en aprobacion" ya confirmaria que la cotizacion existe.
         */
        abort_unless(
            (new \App\Dominio\Permisos\AlcanceDeCotizaciones())
                ->puedeVer($cotizacion, auth()->user()),
            404
        );

        if ($cotizacion->puedeSerEditadaPor(auth()->user())) {
            return;
        }

        $motivo = (new \App\Dominio\Aprobaciones\ReglasDeAprobacion())
            ->motivoDelBloqueo($cotizacion, auth()->user());

        Notification::make()
            ->warning()
            ->title('Cotización bloqueada')
            ->body($motivo)
            ->persistent()
            ->send();

        $this->redirect(CotizacionResource::getUrl('view', ['record' => $cotizacion]));
    }

    /**
     * Las siete categorias adicionales, cada una en su seccion plegada.
     *
     * Filament las apila una debajo de otra: siete tablas seguidas hacen
     * una pagina que no acaba. Plegadas se ven los siete titulos de un
     * vistazo y se abre solo la que se necesita.
     *
     * Se construyen aqui, replicando lo que hace el padre, y no se
     * declaran en el grupo: `modifyTabUsing` se evalua **antes** de que la
     * pagina añada el contenido, asi que alli el esquema llega vacio.
     */
    public function getRelationManagerTabComponent(string | int $key): ?\Filament\Schemas\Components\Tabs\Tab
    {
        $gestor = $this->getCachedRelationManagers()[$key] ?? null;

        if (! ($gestor instanceof \Filament\Resources\RelationManagers\RelationGroup)) {
            return parent::getRelationManagerTabComponent($key);
        }

        $gestor->ownerRecord($this->getRecord())->pageClass(static::class);

        $datos = $this->getRelationManagerLivewireData();

        $secciones = [];

        foreach (array_values($gestor->getManagers()) as $posicion => $clase) {
            [$titulo, $icono] = \App\Filament\Resources\Cotizacions\RelationManagers\CategoriasAdicionales::etiqueta($posicion);

            $secciones[] = \Filament\Schemas\Components\Section::make($titulo)
                ->icon($icono)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    \Filament\Schemas\Components\Livewire::make(
                        $clase,
                        [...$datos, ...$clase::getDefaultProperties()],
                    )->key("categoria-{$posicion}"),
                ]);
        }

        return $gestor->getTabComponent()->schema($secciones);
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
