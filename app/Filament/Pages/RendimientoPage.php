<?php

namespace App\Filament\Pages;

use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Dominio\Rendimiento\Rendimiento;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Que pantallas van lentas y por que.
 *
 * Sin consola en el servidor, esta es la unica forma de ver si algo se
 * degrado tras un despliegue. Lo importante no es el reloj sino el numero
 * de consultas: una pantalla cuyas consultas crecen con los datos es un
 * N+1, que es lo que hacia que el sistema actual tardara 35 segundos en
 * abrir una cotizacion.
 */
class RendimientoPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Rendimiento';

    protected static ?int $navigationSort = 94;

    protected static ?string $slug = 'rendimiento';

    protected string $view = 'filament.pages.rendimiento';

    public function getTitle(): string|Htmlable
    {
        return 'Rendimiento';
    }

    public function getSubheading(): ?string
    {
        return 'Solo se anotan las peticiones que pasan de medio segundo. Si está vacío, nada va lento.';
    }

    public static function canAccess(): bool
    {
        return (new Permisos())->puedeElActual(Capacidad::ConsultarLaAuditoria);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403, Capacidad::ConsultarLaAuditoria->motivoDelBloqueo());
    }

    /** @return array{lentas: int, peor_ms: int, con_muchas_consultas: int, desde: ?string} */
    public function resumen(): array
    {
        return app(Rendimiento::class)->resumen();
    }

    /** @return array<int, object> */
    public function rutasMasLentas(): array
    {
        return app(Rendimiento::class)->rutasMasLentas()->all();
    }

    /** @return array<int, object> */
    public function sospechosas(): array
    {
        return app(Rendimiento::class)->sospechosasDeNMasUno()->all();
    }

    public function umbralDeConsultas(): int
    {
        return Rendimiento::CONSULTAS_SOSPECHOSAS;
    }

    public function diasQueSeGuardan(): int
    {
        return Rendimiento::DIAS_QUE_SE_GUARDAN;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('purgar')
                ->label('Borrar lo anterior a ' . Rendimiento::DIAS_QUE_SE_GUARDAN . ' días')
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Solo borra las mediciones viejas. No afecta a las cotizaciones.')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    $borradas = app(Rendimiento::class)->purgar();

                    Notification::make()
                        ->success()
                        ->title($borradas === 0 ? 'No había nada viejo que borrar' : "Se borraron {$borradas} mediciones")
                        ->send();
                }),
        ];
    }
}
