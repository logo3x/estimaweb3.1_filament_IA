<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota las peticiones que tardaron demasiado.
 *
 * Solo las lentas: registrar todas llenaria la tabla de ruido y la propia
 * escritura costaria mas que lo que se mide.
 *
 * Lo que se busca con esto no es el reloj, sino **el numero de consultas**:
 * una pantalla cuyas consultas crecen con los datos es un N+1, y eso no se
 * nota en local con pocos registros. Es exactamente lo que hacia que el
 * sistema actual tardara 35 segundos en abrir una cotizacion.
 */
class MedirLaPeticion
{
    /** A partir de aqui se considera lenta. */
    private const UMBRAL_MS = 500;

    /**
     * Si la tabla existe.
     *
     * Se comprueba una vez por proceso: sin esto, cada peticion lanzaria
     * una consulta al esquema, y durante las migraciones -cuando la tabla
     * aun no existe- la aplicacion reventaria en cada peticion.
     */
    private static ?bool $hayTabla = null;

    public function handle(Request $request, Closure $next): Response
    {
        $arranque = microtime(true);

        $consultas = 0;
        $msEnConsultas = 0.0;

        DB::listen(function ($consulta) use (&$consultas, &$msEnConsultas): void {
            $consultas++;
            $msEnConsultas += $consulta->time;
        });

        $respuesta = $next($request);

        $ms = (microtime(true) - $arranque) * 1000;

        if ($ms >= self::UMBRAL_MS) {
            $this->anotar($request, $respuesta, $ms, $consultas, $msEnConsultas);
        }

        return $respuesta;
    }

    private function anotar(
        Request $request,
        Response $respuesta,
        float $ms,
        int $consultas,
        float $msEnConsultas,
    ): void {
        if (! $this->hayTabla()) {
            return;
        }

        try {
            DB::table('peticiones_lentas')->insert([
                'metodo' => $request->method(),
                'ruta' => mb_substr($request->path(), 0, 500),
                'milisegundos' => (int) round($ms),
                'consultas' => $consultas,
                'milisegundos_en_consultas' => (int) round($msEnConsultas),
                'memoria_kb' => (int) round(memory_get_peak_usage(true) / 1024),
                'codigo' => $respuesta->getStatusCode(),
                'usuario_id' => auth()->id(),
                'es_livewire' => $request->hasHeader('X-Livewire'),
                'cuando' => now(),
            ]);
        } catch (\Throwable) {
            /**
             * Medir no puede romper la peticion que se esta midiendo.
             *
             * Sin catch, un fallo al escribir aqui daria pantalla de error
             * en una peticion que por lo demas funciono.
             */
        }
    }

    private function hayTabla(): bool
    {
        if (self::$hayTabla === null) {
            try {
                self::$hayTabla = Schema::hasTable('peticiones_lentas');
            } catch (\Throwable) {
                self::$hayTabla = false;
            }
        }

        return self::$hayTabla;
    }
}
