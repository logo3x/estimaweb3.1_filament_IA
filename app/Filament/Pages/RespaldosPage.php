<?php

namespace App\Filament\Pages;

use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Dominio\Respaldos\Respaldos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Respaldos de la base, desde el panel.
 *
 * El hosting no tiene consola, asi que sin esta pantalla no habria forma de
 * generar ni descargar un respaldo. El respaldo automatico lo lanza el cron
 * de cPanel; esta pagina sirve para hacer uno antes de algo arriesgado y,
 * sobre todo, para **bajarse el archivo**: un respaldo que vive en el mismo
 * servidor no protege si se pierde el servidor.
 */
class RespaldosPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Respaldos';

    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'respaldos';

    protected string $view = 'filament.pages.respaldos';

    public function getTitle(): string|Htmlable
    {
        return 'Respaldos de la base de datos';
    }

    public function getSubheading(): ?string
    {
        return 'Descargue una copia y guárdela fuera del servidor: un respaldo '
            . 'que vive en el mismo sitio no sirve si se pierde el servidor.';
    }

    public static function canAccess(): bool
    {
        return (new Permisos())->puedeElActual(Capacidad::GestionarRespaldos);
    }

    /** Se vuelve a comprobar: una peticion de Livewire no pasa por el menu. */
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403, Capacidad::GestionarRespaldos->motivoDelBloqueo());
    }

    /**
     * Los respaldos que hay ahora mismo.
     *
     * @return array<int, array{nombre: string, peso: string, cuando: string, hace: string}>
     */
    public function respaldos(): array
    {
        return app(Respaldos::class)->listar()
            ->map(fn (array $r): array => [
                'nombre' => $r['nombre'],
                'peso' => $this->enPalabras($r['bytes']),
                'cuando' => $r['cuando']->format('d/m/Y H:i'),
                'hace' => $r['cuando']->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Cuanto hace del ultimo, para avisar si hace demasiado.
     *
     * Es el dato que importa: tener respaldos viejos y creer que estan al
     * dia es la forma habitual de perder datos.
     */
    public function avisoDeAntiguedad(): ?string
    {
        $ultimo = app(Respaldos::class)->ultimo();

        if ($ultimo === null) {
            return 'Todavía no hay ningún respaldo. Cree uno ahora.';
        }

        $dias = $ultimo->diffInDays(now());

        if ($dias >= 7) {
            return "El último respaldo es de hace {$dias} días. Debería haber uno diario.";
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('crear')
                ->label('Crear respaldo ahora')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    try {
                        $resumen = app(Respaldos::class)->crear();
                    } catch (\Throwable $e) {
                        report($e);

                        Notification::make()
                            ->danger()
                            ->title('No se pudo crear el respaldo')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Respaldo creado')
                        ->body(sprintf(
                            '%s tablas y %s filas, %s. Descárguelo y guárdelo fuera del servidor.',
                            number_format($resumen['tablas'], 0, ',', '.'),
                            number_format($resumen['filas'], 0, ',', '.'),
                            $this->enPalabras($resumen['bytes']),
                        ))
                        ->send();
                }),
        ];
    }

    /** Baja un respaldo al equipo de quien lo pide. */
    public function descargar(string $nombre): ?BinaryFileResponse
    {
        abort_unless(static::canAccess(), 403, Capacidad::GestionarRespaldos->motivoDelBloqueo());

        $ruta = app(Respaldos::class)->rutaDe($nombre);

        if ($ruta === null) {
            Notification::make()
                ->danger()
                ->title('Ese respaldo ya no está disponible')
                ->send();

            return null;
        }

        return response()->download($ruta);
    }

    public function borrar(string $nombre): void
    {
        abort_unless(static::canAccess(), 403, Capacidad::GestionarRespaldos->motivoDelBloqueo());

        if (app(Respaldos::class)->borrar($nombre)) {
            Notification::make()->success()->title('Respaldo borrado')->send();

            return;
        }

        Notification::make()->danger()->title('No se pudo borrar')->send();
    }

    private function enPalabras(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
