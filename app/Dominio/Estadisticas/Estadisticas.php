<?php

namespace App\Dominio\Estadisticas;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\CalcularCotizacion;
use App\Dominio\Permisos\AlcanceDeCotizaciones;
use App\Models\Cotizacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Las cifras de conjunto: cuanto se cotiza, a quien y como avanza.
 *
 * Tres diferencias deliberadas con la pagina del sistema actual:
 *
 * - **Los montos se CALCULAN, no se leen de la base.** Alli se sumaba la
 *   columna `aiu_valortotal_con_iva`, que solo se reescribe cuando alguien
 *   abre el paso del AIU: hay cotizaciones con el total de una version vieja
 *   del codigo (unas ~19 % infladas por el IVA guardado dos veces), otras con
 *   un total de datos que ya no existen y otras en cero porque nunca se
 *   calcularon. Aqui sale el mismo numero que la oferta.
 * - **El estado sale del flujo de aprobacion**, no de la columna `estado`,
 *   que esta vacia en 46 de las 48 cotizaciones.
 * - **Respeta el alcance por gerencia**, igual que el listado.
 *
 * Calcular todas las cotizaciones cuesta unos 450 ms y mas de mil consultas,
 * de modo que el resultado se guarda una hora. `olvidar()` lo rehace.
 */
final class Estadisticas
{
    /** Lo que dura la cache: las cifras de conjunto no cambian de un minuto a otro. */
    public const SEGUNDOS_EN_CACHE = 3600;

    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function __construct(
        private readonly CalcularCotizacion $calculo,
        private readonly AlcanceDeCotizaciones $alcance = new AlcanceDeCotizaciones,
    ) {}

    public static function crear(): self
    {
        return new self(CalcularCotizacion::crear());
    }

    /**
     * Las cifras para un usuario, de la cache si estan.
     *
     * @return array<string, mixed>
     */
    public function para(User $usuario, ?CarbonInterface $hoy = null): array
    {
        return Cache::remember(
            $this->claveDe($usuario),
            self::SEGUNDOS_EN_CACHE,
            fn (): array => $this->calcular($usuario, $hoy ?? CarbonImmutable::now()),
        );
    }

    /** Descarta las cifras guardadas, para recalcular en la siguiente consulta. */
    public function olvidar(User $usuario): void
    {
        Cache::forget($this->claveDe($usuario));
    }

    /**
     * Las cifras, recien calculadas.
     *
     * @return array{
     *     calculado: string,
     *     conteos: array{total: int, mes: int, mes_anterior: int, anio: int, variacion_mes: ?float},
     *     valores: array{total: float, mes: float, anio: float, promedio: float},
     *     por_estado: list<array{estado: string, etiqueta: string, color: string, cantidad: int, valor: float}>,
     *     aprobacion: array{aprobadas: int, rechazadas: int, en_tramite: int, borradores: int, tasa: ?float},
     *     por_mes: list<array{mes: string, cantidad: int, valor: float}>,
     *     clientes: list<array{cliente: string, cantidad: int, valor: float}>,
     *     recientes: list<array{id: int, codigo: string, nombre: string, cliente: string, fecha: string, estado: string, color: string, total: float}>,
     *     con_avisos: list<array{id: int, codigo: string, avisos: int}>
     * }
     */
    public function calcular(User $usuario, CarbonInterface $hoy): array
    {
        $filas = $this->filas($usuario);

        $inicioMes = $hoy->copy()->startOfMonth();
        $inicioMesAnterior = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
        $inicioAnio = $hoy->copy()->startOfYear();

        $delMes = $filas->filter(fn (array $f): bool => $f['creada']->gte($inicioMes));
        $delMesAnterior = $filas->filter(fn (array $f): bool => $f['creada']->gte($inicioMesAnterior) && $f['creada']->lt($inicioMes));
        $delAnio = $filas->filter(fn (array $f): bool => $f['creada']->gte($inicioAnio));

        return [
            'calculado' => $hoy->format('Y-m-d H:i'),
            'conteos' => [
                'total' => $filas->count(),
                'mes' => $delMes->count(),
                'mes_anterior' => $delMesAnterior->count(),
                'anio' => $delAnio->count(),
                'variacion_mes' => $delMesAnterior->isEmpty()
                    ? null
                    : ($delMes->count() - $delMesAnterior->count()) / $delMesAnterior->count() * 100,
            ],
            'valores' => [
                'total' => (float) $filas->sum('total'),
                'mes' => (float) $delMes->sum('total'),
                'anio' => (float) $delAnio->sum('total'),
                'promedio' => $filas->isEmpty() ? 0.0 : (float) $filas->avg('total'),
            ],
            'por_estado' => $this->porEstado($filas),
            'aprobacion' => $this->aprobacion($filas),
            'por_mes' => $this->porMes($delAnio),
            'clientes' => $this->clientes($filas),
            'recientes' => $this->recientes($filas),
            'con_avisos' => $filas
                ->filter(fn (array $f): bool => $f['avisos'] > 0)
                ->map(fn (array $f): array => ['id' => $f['id'], 'codigo' => $f['codigo'], 'avisos' => $f['avisos']])
                ->values()
                ->all(),
        ];
    }

    /**
     * Cada cotizacion visible, con su estado y su total calculado.
     *
     * @return Collection<int, array{id: int, codigo: string, nombre: string, cliente: string, creada: CarbonInterface, estado: EstadoAprobacion, total: float, avisos: int}>
     */
    private function filas(User $usuario): Collection
    {
        return $this->alcance
            ->aplicar(Cotizacion::query(), $usuario)
            ->with(['items', 'aiu'])
            ->orderBy('id')
            ->get()
            ->map(function (Cotizacion $cotizacion): array {
                $calculada = $this->calculo->para($cotizacion);

                return [
                    'id' => (int) $cotizacion->id,
                    'codigo' => (string) $cotizacion->codigo,
                    'nombre' => trim((string) $cotizacion->nombre),
                    /** Los nombres llegan con espacios de mas: "PAREX " y "PAREX" son el mismo cliente. */
                    'cliente' => trim((string) $cotizacion->cliente) ?: 'Sin cliente',
                    'creada' => CarbonImmutable::parse($cotizacion->created_at),
                    'estado' => EstadoAprobacion::desdeColumnas($cotizacion->aprobacion1, $cotizacion->aprobacion2),
                    'total' => $calculada->totales->total,
                    'avisos' => count($calculada->avisos),
                ];
            });
    }

    /** @return list<array{estado: string, etiqueta: string, color: string, cantidad: int, valor: float}> */
    private function porEstado(Collection $filas): array
    {
        $resultado = [];

        foreach (EstadoAprobacion::cases() as $estado) {
            $delEstado = $filas->filter(fn (array $f): bool => $f['estado'] === $estado);

            if ($delEstado->isEmpty()) {
                continue;
            }

            $resultado[] = [
                'estado' => $estado->value,
                'etiqueta' => $estado->etiquetaCorta(),
                'color' => $estado->color(),
                'cantidad' => $delEstado->count(),
                'valor' => (float) $delEstado->sum('total'),
            ];
        }

        return $resultado;
    }

    /**
     * Como terminan las que ya se decidieron.
     *
     * La tasa cuenta solo las cerradas: una cotizacion en tramite todavia
     * no ha ganado ni perdido.
     *
     * @return array{aprobadas: int, rechazadas: int, en_tramite: int, borradores: int, tasa: ?float}
     */
    private function aprobacion(Collection $filas): array
    {
        $aprobadas = $filas->filter(fn (array $f): bool => $f['estado'] === EstadoAprobacion::Aprobada)->count();
        $rechazadas = $filas->filter(fn (array $f): bool => in_array($f['estado'], [EstadoAprobacion::RechazadaTecnica, EstadoAprobacion::RechazadaFinanciera], true))->count();
        $decididas = $aprobadas + $rechazadas;

        return [
            'aprobadas' => $aprobadas,
            'rechazadas' => $rechazadas,
            'en_tramite' => $filas->filter(fn (array $f): bool => $f['estado']->estaEnTramite())->count(),
            'borradores' => $filas->filter(fn (array $f): bool => $f['estado'] === EstadoAprobacion::Borrador)->count(),
            'tasa' => $decididas === 0 ? null : $aprobadas / $decididas * 100,
        ];
    }

    /**
     * Los doce meses del año en curso, tambien los que no tienen nada.
     *
     * Un mes en cero es un dato: sin el, la grafica salta de marzo a junio
     * y parece que no paso nada raro.
     *
     * @return list<array{mes: string, cantidad: int, valor: float}>
     */
    private function porMes(Collection $delAnio): array
    {
        $porMes = [];

        foreach (self::MESES as $numero => $nombre) {
            $delMes = $delAnio->filter(fn (array $f): bool => $f['creada']->month === $numero + 1);

            $porMes[] = [
                'mes' => $nombre,
                'cantidad' => $delMes->count(),
                'valor' => (float) $delMes->sum('total'),
            ];
        }

        return $porMes;
    }

    /**
     * Los cinco clientes con mas valor cotizado.
     *
     * Por valor y no por numero de cotizaciones, al reves que el sistema
     * actual: cinco ofertas pequeñas pesan menos que una grande.
     *
     * @return list<array{cliente: string, cantidad: int, valor: float}>
     */
    private function clientes(Collection $filas): array
    {
        return $filas
            ->groupBy(fn (array $f): string => mb_strtoupper($f['cliente']))
            ->map(fn (Collection $deCliente): array => [
                'cliente' => $deCliente->first()['cliente'],
                'cantidad' => $deCliente->count(),
                'valor' => (float) $deCliente->sum('total'),
            ])
            ->sortByDesc('valor')
            ->take(5)
            ->values()
            ->all();
    }

    /** @return list<array{id: int, codigo: string, nombre: string, cliente: string, fecha: string, estado: string, color: string, total: float}> */
    private function recientes(Collection $filas): array
    {
        return $filas
            ->sortByDesc(fn (array $f): string => $f['creada']->format('YmdHis').str_pad((string) $f['id'], 8, '0', STR_PAD_LEFT))
            ->take(6)
            ->map(fn (array $f): array => [
                'id' => $f['id'],
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'cliente' => $f['cliente'],
                'fecha' => $f['creada']->format('Y-m-d'),
                'estado' => $f['estado']->etiquetaCorta(),
                'color' => $f['estado']->color(),
                'total' => $f['total'],
            ])
            ->values()
            ->all();
    }

    private function claveDe(User $usuario): string
    {
        return 'estadisticas:v1:'.($this->alcance->veTodas($usuario) ? 'todas' : 'usuario-'.$usuario->id);
    }
}
