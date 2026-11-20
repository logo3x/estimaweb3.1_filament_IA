<?php

namespace App\Filament\Pages;

use App\Dominio\Estadisticas\Estadisticas;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Las cifras de conjunto: cuanto se cotiza, a quien y como avanza.
 *
 * Los montos salen del mismo calculo que la oferta, no de las columnas
 * guardadas: ver App\Dominio\Estadisticas\Estadisticas.
 */
class EstadisticasPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Estadísticas';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'estadisticas';

    protected string $view = 'filament.pages.estadisticas';

    /** @var array<string, mixed>|null */
    private ?array $cifras = null;

    public function getTitle(): string|Htmlable
    {
        return 'Estadísticas de cotizaciones';
    }

    public function getSubheading(): ?string
    {
        return 'Los valores son los de la oferta, con AIU e IVA, recalculados. Se actualizan cada hora.';
    }

    public static function canAccess(): bool
    {
        return (new Permisos)->puedeElActual(Capacidad::ConsultarLasEstadisticas);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403, Capacidad::ConsultarLasEstadisticas->motivoDelBloqueo());
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('actualizar')
                ->label('Actualizar ahora')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    Estadisticas::crear()->olvidar(auth()->user());
                    $this->cifras = null;

                    Notification::make()
                        ->success()
                        ->title('Cifras recalculadas')
                        ->send();
                }),
        ];
    }

    /** @return array<string, mixed> */
    public function cifras(): array
    {
        return $this->cifras ??= Estadisticas::crear()->para(auth()->user());
    }

    public function urlDe(int $cotizacionId): string
    {
        return CotizacionResource::getUrl('view', ['record' => $cotizacionId]);
    }

    /** Pesos sin decimales y con punto de miles, como en la oferta. */
    public function pesos(float $valor): string
    {
        return '$'.number_format($valor, 0, ',', '.');
    }

    /** Los montos grandes, abreviados para que quepan en una tarjeta: "$2.751 M". */
    public function pesosCortos(float $valor): string
    {
        if (abs($valor) >= 1_000_000) {
            return '$'.number_format($valor / 1_000_000, $valor >= 100_000_000 ? 0 : 1, ',', '.').' M';
        }

        return $this->pesos($valor);
    }
}
