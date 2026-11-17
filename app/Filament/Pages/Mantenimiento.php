<?php

namespace App\Filament\Pages;

use App\Dominio\Mantenimiento\Mantenimiento as Tareas;
use App\Dominio\Mantenimiento\TareaDeMantenimiento;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Mantenimiento del servidor, desde el propio panel.
 *
 * El hosting es cPanel **sin consola**: no hay forma de escribir
 * `php artisan` alli. Sin esta pagina, tras cada despliegue habria que
 * pedirle al proveedor que ejecutara los comandos, o el sitio se quedaria
 * sirviendo el codigo viejo desde las caches.
 *
 * Tambien dice como esta el servidor, que es lo primero que se mira cuando
 * algo falla y no se puede entrar a mirar.
 */
class Mantenimiento extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Mantenimiento';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'mantenimiento';

    protected string $view = 'filament.pages.mantenimiento';

    public function getTitle(): string|Htmlable
    {
        return 'Mantenimiento del sistema';
    }

    public function getSubheading(): ?string
    {
        return 'En el servidor no hay consola: lo que haría falta ejecutar a '
            . 'mano tras un despliegue se dispara desde aquí.';
    }

    /**
     * Solo la administracion total llega aqui.
     *
     * Filament consulta esto para el menu y para la ruta: sin ello,
     * cualquiera con acceso al panel podria abrirla escribiendo la URL.
     */
    public static function canAccess(): bool
    {
        return (new Permisos())->puedeElActual(Capacidad::MantenerElSistema);
    }

    /**
     * Y se vuelve a comprobar al entrar.
     *
     * Una peticion de Livewire puede llegar sin pasar por el menu.
     */
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403, Capacidad::MantenerElSistema->motivoDelBloqueo());
    }

    /**
     * Como esta el servidor ahora mismo.
     *
     * @return array<string, array{valor: string, bien: bool, nota: string}>
     */
    public function diagnostico(): array
    {
        return (new Tareas())->diagnostico();
    }

    /** Cuantas cosas hay que atender. */
    public function cuantosAvisos(): int
    {
        return count(array_filter(
            $this->diagnostico(),
            fn (array $punto): bool => ! $punto['bien'],
        ));
    }

    /**
     * Un boton por tarea.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_map(
            fn (TareaDeMantenimiento $tarea): Action => $this->botonDe($tarea),
            TareaDeMantenimiento::cases(),
        );
    }

    private function botonDe(TareaDeMantenimiento $tarea): Action
    {
        $accion = Action::make($tarea->value)
            ->label($tarea->etiqueta())
            ->icon($tarea->icono())
            ->color($tarea === TareaDeMantenimiento::RehacerCaches ? 'primary' : 'gray')
            ->outlined($tarea !== TareaDeMantenimiento::RehacerCaches)
            ->action(function () use ($tarea): void {
                /** Se vuelve a comprobar: esconder el boton no es protección. */
                abort_unless(static::canAccess(), 403);

                $resultado = (new Tareas())->ejecutar($tarea);

                Notification::make()
                    ->status($resultado->salioBien ? 'success' : 'danger')
                    ->title($resultado->titulo())
                    ->body($resultado->resumen())
                    ->persistent($resultado->salioBien === false)
                    ->send();
            });

        if ($tarea->pideConfirmacion()) {
            $accion
                ->requiresConfirmation()
                ->modalHeading($tarea->etiqueta())
                ->modalDescription($tarea->cuandoHaceFalta())
                ->modalSubmitActionLabel('Ejecutar');
        }

        return $accion;
    }

    /** Las tareas, para describirlas en la pantalla. */
    public function tareas(): array
    {
        return TareaDeMantenimiento::cases();
    }
}
