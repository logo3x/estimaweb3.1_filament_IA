<?php

namespace App\Filament\Widgets;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\Aprobaciones\ReglasDeAprobacion;
use App\Dominio\Permisos\AlcanceDeCotizaciones;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use App\Models\Cotizacion;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * El inicio: a quien saluda y que tiene pendiente.
 *
 * El sistema anterior saludaba con el nombre, la gerencia y la fecha. Aqui
 * ademas se dice lo que importa al entrar: que cotizaciones esperan una
 * decision de quien entra y cuales suyas estan en borrador o devueltas.
 * Sin esto habia que abrir el listado y filtrar para saberlo.
 */
class Bienvenida extends Widget
{
    protected string $view = 'filament.widgets.bienvenida';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    /** Sin carga diferida: es lo primero que se ve, y un hueco que luego se llena parpadea. */
    protected static bool $isLazy = false;

    /** La hora de la oficina: la aplicacion guarda en UTC y a las 7 p. m. de Bogota diria "buenos dias". */
    private const ZONA = 'America/Bogota';

    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    private const CUANTAS = 5;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $usuario = auth()->user();
        $ahora = CarbonImmutable::now(self::ZONA);

        return [
            'saludo' => match (true) {
                $ahora->hour < 12 => 'Buenos días',
                $ahora->hour < 19 => 'Buenas tardes',
                default => 'Buenas noches',
            },
            'nombre' => strtok(trim((string) $usuario->name), ' ') ?: 'de nuevo',
            'gerencia' => $usuario->gerenciaRelacion?->nombre ?? $usuario->gerencia,
            'fecha' => ucfirst(self::DIAS[$ahora->dayOfWeek]).' '.$ahora->day.' de '.self::MESES[$ahora->month - 1].' de '.$ahora->year,
            'porDecidir' => $this->porDecidir(),
            'mias' => $this->misPendientes(),
            'puedeCrear' => (new Permisos)->puede($usuario, Capacidad::CrearCotizaciones),
            'urlNueva' => CotizacionResource::getUrl('create'),
            'urlListado' => CotizacionResource::getUrl('index'),
        ];
    }

    /**
     * Las que esperan una decision que puede tomar quien entra.
     *
     * Se buscan las que estan en tramite y luego se pregunta a las reglas
     * del flujo, una por una: son las mismas que deciden si aparece el
     * boton de aprobar, asi que aqui no puede salir algo que alli no.
     *
     * @return array{total: int, lista: list<array{codigo: string, nombre: string, cliente: string, estado: string, color: string, url: string}>}
     */
    private function porDecidir(): array
    {
        $usuario = auth()->user();
        $reglas = new ReglasDeAprobacion;

        $enTramite = $this->visibles()
            ->where(fn ($q) => EstadoAprobacion::EsperandoTecnica->aplicarFiltro($q)
                ->orWhere(fn ($o) => EstadoAprobacion::EsperandoFinanciera->aplicarFiltro($o)))
            ->latest('updated_at')
            ->get()
            ->filter(fn (Cotizacion $c): bool => $reglas->puedeDecidirTecnica($c, $usuario) || $reglas->puedeDecidirFinanciera($c, $usuario));

        return ['total' => $enTramite->count(), 'lista' => $this->resumir($enTramite->take(self::CUANTAS))];
    }

    /**
     * Las suyas que necesitan algo de su parte: borradores y devueltas.
     *
     * @return array{total: int, lista: list<array{codigo: string, nombre: string, cliente: string, estado: string, color: string, url: string}>}
     */
    private function misPendientes(): array
    {
        $mias = $this->visibles()
            ->where('id_user', auth()->id())
            ->where(fn ($q) => EstadoAprobacion::Borrador->aplicarFiltro($q)
                ->orWhere(fn ($o) => EstadoAprobacion::RechazadaTecnica->aplicarFiltro($o))
                ->orWhere(fn ($o) => EstadoAprobacion::RechazadaFinanciera->aplicarFiltro($o)))
            ->latest('updated_at')
            ->get();

        return ['total' => $mias->count(), 'lista' => $this->resumir($mias->take(self::CUANTAS))];
    }

    /** @return Builder<Cotizacion> */
    private function visibles()
    {
        return (new AlcanceDeCotizaciones)->aplicar(Cotizacion::query(), auth()->user());
    }

    /**
     * @param  iterable<Cotizacion>  $cotizaciones
     * @return list<array{codigo: string, nombre: string, cliente: string, estado: string, color: string, url: string}>
     */
    private function resumir(iterable $cotizaciones): array
    {
        $resumen = [];

        foreach ($cotizaciones as $cotizacion) {
            $estado = EstadoAprobacion::desdeColumnas($cotizacion->aprobacion1, $cotizacion->aprobacion2);

            $resumen[] = [
                'codigo' => (string) $cotizacion->codigo,
                'nombre' => trim((string) $cotizacion->nombre),
                'cliente' => trim((string) $cotizacion->cliente),
                'estado' => $estado->etiquetaCorta(),
                'color' => $estado->color(),
                'url' => $cotizacion->puedeSerEditadaPor(auth()->user())
                    ? CotizacionResource::getUrl('edit', ['record' => $cotizacion])
                    : CotizacionResource::getUrl('view', ['record' => $cotizacion]),
            ];
        }

        return $resumen;
    }
}
