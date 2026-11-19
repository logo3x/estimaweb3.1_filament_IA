<?php

namespace App\Dominio\Rendimiento;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lee lo que anoto el middleware y lo resume.
 *
 * La pregunta que responde no es "cuanto tarda" sino **que pantalla tarda y
 * por que**: el numero de consultas delata un N+1, que es el problema que
 * crece con los datos y no se ve en local.
 */
class Rendimiento
{
    /** Cuantos dias se conservan. Mas atras no sirve para decidir nada. */
    public const DIAS_QUE_SE_GUARDAN = 30;

    /** Por encima de esto, la pantalla casi seguro tiene un N+1. */
    public const CONSULTAS_SOSPECHOSAS = 60;

    /**
     * Las rutas mas lentas, agrupadas.
     *
     * Agrupadas y no una lista de peticiones sueltas: lo que importa es si
     * una pantalla **siempre** va lenta, no que una vez tardara.
     *
     * @return Collection<int, object>
     */
    public function rutasMasLentas(int $cuantas = 15): Collection
    {
        return DB::table('peticiones_lentas')
            ->selectRaw('ruta, metodo, es_livewire')
            ->selectRaw('COUNT(*) as veces')
            ->selectRaw('ROUND(AVG(milisegundos)) as media_ms')
            ->selectRaw('MAX(milisegundos) as peor_ms')
            ->selectRaw('ROUND(AVG(consultas)) as media_consultas')
            ->selectRaw('MAX(consultas) as peor_consultas')
            ->where('cuando', '>=', now()->subDays(self::DIAS_QUE_SE_GUARDAN))
            ->groupBy('ruta', 'metodo', 'es_livewire')
            ->orderByDesc('media_ms')
            ->limit($cuantas)
            ->get();
    }

    /**
     * Como va la cosa, en cuatro numeros.
     *
     * @return array{lentas: int, peor_ms: int, con_muchas_consultas: int, desde: ?string}
     */
    public function resumen(): array
    {
        $desde = now()->subDays(self::DIAS_QUE_SE_GUARDAN);

        $fila = DB::table('peticiones_lentas')
            ->where('cuando', '>=', $desde)
            ->selectRaw('COUNT(*) as lentas')
            ->selectRaw('COALESCE(MAX(milisegundos), 0) as peor_ms')
            ->selectRaw('SUM(CASE WHEN consultas >= ? THEN 1 ELSE 0 END) as con_muchas_consultas', [self::CONSULTAS_SOSPECHOSAS])
            ->selectRaw('MIN(cuando) as primera')
            ->first();

        return [
            'lentas' => (int) ($fila->lentas ?? 0),
            'peor_ms' => (int) ($fila->peor_ms ?? 0),
            'con_muchas_consultas' => (int) ($fila->con_muchas_consultas ?? 0),
            'desde' => $fila->primera ?? null,
        ];
    }

    /**
     * Las que tienen pinta de N+1.
     *
     * @return Collection<int, object>
     */
    public function sospechosasDeNMasUno(): Collection
    {
        return DB::table('peticiones_lentas')
            ->selectRaw('ruta, MAX(consultas) as consultas, ROUND(AVG(milisegundos)) as media_ms, COUNT(*) as veces')
            ->where('cuando', '>=', now()->subDays(self::DIAS_QUE_SE_GUARDAN))
            ->groupBy('ruta')
            ->havingRaw('MAX(consultas) >= ?', [self::CONSULTAS_SOSPECHOSAS])
            ->orderByDesc('consultas')
            ->limit(10)
            ->get();
    }

    /**
     * Borra lo viejo.
     *
     * Sin esto la tabla crece sin limite: en el sistema anterior el comando
     * de purga existia pero no estaba programado, de modo que no corria
     * nunca.
     *
     * @return int Cuantas filas se borraron.
     */
    public function purgar(?int $dias = null): int
    {
        return DB::table('peticiones_lentas')
            ->where('cuando', '<', now()->subDays($dias ?? self::DIAS_QUE_SE_GUARDAN))
            ->delete();
    }

    /** Cuantas filas hay guardadas. */
    public function cuantasHay(): int
    {
        return DB::table('peticiones_lentas')->count();
    }
}
