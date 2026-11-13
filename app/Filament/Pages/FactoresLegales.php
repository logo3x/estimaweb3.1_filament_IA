<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrigenFactors\Schemas\OrigenFactorForm;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\OrigenFactor;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Factores legales de nomina.
 *
 * Es una sola fila, asi que no tiene sentido un listado: se edita
 * directamente. De aqui sale el costo de todo el personal de todas las
 * cotizaciones, de modo que un valor mal escrito desplaza el precio de la
 * empresa entera.
 */
class FactoresLegales extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static UnitEnum|string|null $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Factores legales';

    protected static ?int $navigationSort = 18;

    protected static ?string $slug = 'factores-legales';

    protected string $view = 'filament.pages.factores-legales';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return 'Factores legales de nómina';
    }

    /**
     * Solo la administracion llega a esta pagina.
     *
     * Filament consulta esto para el menu lateral y para la ruta: sin ello,
     * cualquiera con acceso al panel podria abrirla escribiendo la URL.
     */
    public static function canAccess(): bool
    {
        return (new Permisos())->puedeElActual(Capacidad::AdministrarFactoresLegales);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403, Capacidad::AdministrarFactoresLegales->motivoDelBloqueo());

        $this->form->fill($this->factores()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return OrigenFactorForm::configure($schema)
            ->statePath('data')
            ->model($this->factores());
    }

    public function guardar(): void
    {
        /**
         * Se vuelve a comprobar al guardar, no solo al entrar: una peticion
         * de Livewire puede llegar sin pasar por la pantalla.
         */
        abort_unless(static::canAccess(), 403, Capacidad::AdministrarFactoresLegales->motivoDelBloqueo());

        $this->factores()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Factores actualizados')
            ->body('El cambio afecta al costo de todas las cotizaciones.')
            ->send();
    }

    /**
     * La fila de factores vigentes.
     *
     * Sin ella no se puede liquidar ninguna nomina; si falta, el calculo
     * lanza una excepcion en lugar de multiplicar por cero.
     */
    private function factores(): OrigenFactor
    {
        return OrigenFactor::query()->orderBy('id')->firstOrFail();
    }
}
