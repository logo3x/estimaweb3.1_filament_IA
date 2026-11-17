<?php

namespace App\Dominio\Mantenimiento;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ejecuta las tareas de mantenimiento y dice como esta el servidor.
 *
 * Existe porque el hosting es cPanel **sin consola**: lo que en cualquier
 * otro sitio se resuelve con `php artisan`, aqui hay que poder dispararlo
 * desde el propio panel.
 *
 * Nada de esto decide nada: la pantalla pregunta y esta clase ejecuta,
 * registrando siempre quien lo hizo.
 */
final class Mantenimiento
{
    /**
     * Ejecuta una tarea y cuenta como fue.
     *
     * No lanza excepciones: un fallo aqui no puede dejar al usuario ante
     * una pantalla de error, porque justamente esta pagina es la que se usa
     * cuando algo va mal.
     */
    public function ejecutar(TareaDeMantenimiento $tarea): ResultadoDeMantenimiento
    {
        $inicio = microtime(true);
        $salida = [];

        /**
         * Quien lo pidio, leido ANTES de ejecutar.
         *
         * `optimize:clear` reinicia parte del contenedor y despues
         * `auth()->id()` ya devuelve nulo: el registro quedaba sin decir
         * quien habia tocado el servidor.
         */
        $quien = auth()->id();

        foreach ($tarea->comandos() as [$comando, $parametros]) {
            try {
                Artisan::call($comando, $parametros);

                $texto = trim(Artisan::output());

                $salida[] = $texto !== '' ? $texto : "{$comando}: hecho";
            } catch (Throwable $e) {
                Log::error('Falló una tarea de mantenimiento', [
                    'tarea' => $tarea->value,
                    'comando' => $comando,
                    'error' => $e->getMessage(),
                    'usuario' => $quien,
                ]);

                return ResultadoDeMantenimiento::fallo(
                    $tarea,
                    "Falló «{$comando}»: " . $e->getMessage(),
                    $this->segundosDesde($inicio),
                );
            }
        }

        Log::info('Tarea de mantenimiento ejecutada', [
            'tarea' => $tarea->value,
            'usuario' => $quien,
        ]);

        return ResultadoDeMantenimiento::exito(
            $tarea,
            implode("\n", array_filter($salida)),
            $this->segundosDesde($inicio),
        );
    }

    /**
     * Como esta el servidor ahora mismo.
     *
     * Es lo primero que se mira cuando algo no funciona y no hay consola
     * para averiguarlo.
     *
     * @return array<string, array{valor: string, bien: bool, nota: string}>
     */
    public function diagnostico(): array
    {
        return [
            'Entorno' => $this->comprobarEntorno(),
            'Depuración' => $this->comprobarDepuracion(),
            'Base de datos' => $this->comprobarBaseDeDatos(),
            'Migraciones' => $this->comprobarMigraciones(),
            'Cachés' => $this->comprobarCaches(),
            'Plantilla de la oferta' => $this->comprobarPlantilla(),
            'Archivos subidos' => $this->comprobarEnlaceDeArchivos(),
            'Extensiones de PHP' => $this->comprobarExtensiones(),
            'Permisos de escritura' => $this->comprobarPermisos(),
            'Espacio en disco' => $this->comprobarDisco(),
        ];
    }

    private function comprobarEntorno(): array
    {
        $entorno = app()->environment();

        return $this->punto(
            $entorno . ' · PHP ' . PHP_VERSION,
            $entorno === 'production',
            $entorno === 'production'
                ? 'Correcto para el servidor.'
                : 'En el servidor debería decir «production».',
        );
    }

    private function comprobarDepuracion(): array
    {
        $activa = (bool) config('app.debug');

        return $this->punto(
            $activa ? 'activada' : 'desactivada',
            ! $activa,
            $activa
                ? '⚠ Con esto activo, cualquier error muestra la configuración entera. Póngalo en false.'
                : 'Los errores no exponen la configuración.',
        );
    }

    private function comprobarBaseDeDatos(): array
    {
        try {
            $nombre = DB::connection()->getDatabaseName();
            DB::connection()->getPdo();

            return $this->punto($nombre, true, 'Conecta correctamente.');
        } catch (Throwable $e) {
            return $this->punto('sin conexión', false, $e->getMessage());
        }
    }

    private function comprobarMigraciones(): array
    {
        try {
            Artisan::call('migrate:status');

            $pendientes = substr_count(Artisan::output(), 'Pending');

            return $this->punto(
                $pendientes === 0 ? 'al día' : "{$pendientes} pendiente(s)",
                $pendientes === 0,
                $pendientes === 0
                    ? 'La base tiene la estructura que el código espera.'
                    : 'Use «Aplicar migraciones pendientes».',
            );
        } catch (Throwable $e) {
            return $this->punto('no se pudo comprobar', false, $e->getMessage());
        }
    }

    private function comprobarCaches(): array
    {
        $cacheadas = file_exists(base_path('bootstrap/cache/config.php'));

        return $this->punto(
            $cacheadas ? 'rehechas' : 'sin rehacer',
            $cacheadas,
            $cacheadas
                ? 'El sitio va a su velocidad.'
                : 'Use «Rehacer las cachés»: el sitio va más lento sin ellas.',
        );
    }

    /**
     * La plantilla del Word.
     *
     * No viaja en el repositorio -lleva el membrete de la empresa-, asi que
     * hay que subirla a mano. Sin ella **ningun** Word ni PDF funciona.
     */
    private function comprobarPlantilla(): array
    {
        $ruta = storage_path('app/plantillas/oferta.docx');

        if (! is_file($ruta)) {
            return $this->punto(
                'no está',
                false,
                '⚠ Sin ella fallan TODAS las ofertas. Súbala por el gestor de '
                . 'archivos a storage/app/plantillas/oferta.docx',
            );
        }

        return $this->punto(
            number_format(filesize($ruta) / 1024, 1, ',', '.') . ' KB',
            true,
            'Modificada el ' . date('d/m/Y', (int) filemtime($ruta)) . '.',
        );
    }

    private function comprobarEnlaceDeArchivos(): array
    {
        $enlace = public_path('storage');

        return $this->punto(
            file_exists($enlace) ? 'enlazados' : 'sin enlazar',
            file_exists($enlace),
            file_exists($enlace)
                ? 'Los archivos subidos se ven.'
                : 'Use «Enlazar los archivos subidos».',
        );
    }

    /**
     * Las extensiones que el sistema necesita.
     *
     * En hosting compartido faltan a menudo `zip` y `gd`, y sin ellas la
     * generacion del Word revienta.
     */
    private function comprobarExtensiones(): array
    {
        $necesarias = ['zip', 'gd', 'dom', 'mbstring', 'fileinfo', 'intl'];
        $faltan = array_values(array_filter(
            $necesarias,
            fn (string $ext): bool => ! extension_loaded($ext),
        ));

        return $this->punto(
            $faltan === [] ? 'todas presentes' : 'faltan: ' . implode(', ', $faltan),
            $faltan === [],
            $faltan === []
                ? 'El Word y el PDF pueden generarse.'
                : '⚠ Pídalas al proveedor del hosting: sin ellas falla la oferta.',
        );
    }

    private function comprobarPermisos(): array
    {
        $carpetas = [
            'storage/app' => storage_path('app'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $sinPermiso = array_keys(array_filter(
            $carpetas,
            fn (string $ruta): bool => ! is_writable($ruta),
        ));

        return $this->punto(
            $sinPermiso === [] ? 'correctos' : 'sin escritura: ' . implode(', ', $sinPermiso),
            $sinPermiso === [],
            $sinPermiso === []
                ? 'El sistema puede escribir donde necesita.'
                : '⚠ Ponga esas carpetas en 775 desde el gestor de archivos.',
        );
    }

    private function comprobarDisco(): array
    {
        $libre = @disk_free_space(base_path());

        if ($libre === false) {
            return $this->punto('no se pudo medir', true, 'El hosting no lo informa.');
        }

        $gb = $libre / 1024 ** 3;

        return $this->punto(
            number_format($gb, 2, ',', '.') . ' GB libres',
            $gb > 0.5,
            $gb > 0.5
                ? 'Espacio suficiente.'
                : '⚠ Queda poco: los documentos generados se acumulan en storage/app/ofertas.',
        );
    }

    /** @return array{valor: string, bien: bool, nota: string} */
    private function punto(string $valor, bool $bien, string $nota): array
    {
        return ['valor' => $valor, 'bien' => $bien, 'nota' => $nota];
    }

    private function segundosDesde(float $inicio): float
    {
        return round(microtime(true) - $inicio, 2);
    }
}
