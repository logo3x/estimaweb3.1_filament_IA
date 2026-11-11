<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Carga en la base LOCAL una copia de los datos de produccion.
 *
 * El sistema en produccion (EstimaWeb 3) y este proyecto conviven mientras dura
 * el desarrollo. Para que el cambio sea transparente para el usuario, aqui se
 * trabaja siempre contra los datos mas recientes: se genera un respaldo desde el
 * panel de produccion, se descarga, y este comando lo importa.
 *
 * Los datos viajan en UN SOLO SENTIDO: produccion -> local. Este comando no
 * puede escribir en produccion; se niega a ejecutarse si la conexion no es
 * local y nunca abre una conexion saliente.
 */
class CargarDatosProduccion extends Command
{
    protected $signature = 'datos:cargar
        {archivo? : Ruta del .sql o .zip de respaldo (si se omite, toma el mas reciente de storage/app/respaldos)}
        {--base= : Base local destino (por defecto, la de la conexion actual)}
        {--force : No pedir confirmacion}';

    protected $description = 'Importa a la base local una copia de los datos de produccion';

    /** Tablas que identifican un respaldo de este sistema. */
    private const TABLAS_ESPERADAS = ['cotizaciones', 'aius', 'item_cotizaciones', 'users'];

    /** Unicos hosts aceptados: solo local. */
    private const HOSTS_LOCALES = ['127.0.0.1', 'localhost', '::1'];

    public function handle(): int
    {
        if (! $this->conexionEsLocal()) {
            return self::FAILURE;
        }

        $archivo = $this->resolverArchivo();

        if ($archivo === null) {
            return self::FAILURE;
        }

        $sql = $this->extraerSiEsZip($archivo);

        if ($sql === null) {
            return self::FAILURE;
        }

        if (! $this->respaldoEsDeEsteSistema($sql)) {
            return self::FAILURE;
        }

        $base = (string) ($this->option('base') ?: config('database.connections.mysql.database'));

        if (! $this->option('force') && ! $this->confirm("Se borrara y recreara la base local '{$base}'. Continuar?", false)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        return $this->importar($sql, $base);
    }

    /**
     * Impide cualquier ejecucion contra un servidor remoto.
     */
    private function conexionEsLocal(): bool
    {
        $host = (string) config('database.connections.mysql.host');

        if (! in_array($host, self::HOSTS_LOCALES, true)) {
            $this->error("La conexion apunta a '{$host}', que no es local.");
            $this->line('Este comando solo carga datos EN una base local. Revise DB_HOST en el .env.');

            return false;
        }

        return true;
    }

    /**
     * Usa el archivo indicado o, si no se dio ninguno, el respaldo mas reciente.
     */
    private function resolverArchivo(): ?string
    {
        $archivo = $this->argument('archivo');

        if ($archivo !== null) {
            if (! is_file($archivo)) {
                $this->error("No existe el archivo: {$archivo}");

                return null;
            }

            return (string) $archivo;
        }

        $candidatos = collect(glob(storage_path('app/respaldos/*.{sql,zip}'), GLOB_BRACE) ?: [])
            ->sortByDesc(fn (string $ruta): int => filemtime($ruta) ?: 0);

        if ($candidatos->isEmpty()) {
            $this->error('No hay respaldos en storage/app/respaldos.');
            $this->line('Descargue el respaldo desde el panel de produccion y dejelo en esa carpeta,');
            $this->line('o pase la ruta: php artisan datos:cargar C:\ruta\respaldo.sql');

            return null;
        }

        $elegido = (string) $candidatos->first();

        $this->info('Respaldo mas reciente: ' . basename($elegido)
            . ' (' . date('Y-m-d H:i', filemtime($elegido) ?: 0) . ')');

        return $elegido;
    }

    /**
     * Si el respaldo viene comprimido, extrae el .sql que lleva dentro.
     */
    private function extraerSiEsZip(string $archivo): ?string
    {
        if (! str_ends_with(strtolower($archivo), '.zip')) {
            return $archivo;
        }

        $zip = new \ZipArchive();

        if ($zip->open($archivo) !== true) {
            $this->error('No se pudo abrir el .zip. Si tiene contrasena, extraigalo a mano.');

            return null;
        }

        $destino = storage_path('app/respaldos/extraido');

        if (! is_dir($destino)) {
            mkdir($destino, 0775, true);
        }

        $interno = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string) $zip->getNameIndex($i);

            if (str_ends_with(strtolower($nombre), '.sql')) {
                $interno = $nombre;
                break;
            }
        }

        if ($interno === null) {
            $zip->close();
            $this->error('El .zip no contiene ningun archivo .sql.');

            return null;
        }

        $zip->extractTo($destino, [$interno]);
        $zip->close();

        $this->line('Extraido del zip: ' . $interno);

        return $destino . '/' . $interno;
    }

    /**
     * Verifica que el respaldo sea de EstimaWeb y no de otro proyecto.
     *
     * En la carpeta de descargas conviven respaldos de varios sistemas con
     * nombres parecidos; importar el equivocado destruiria la base local.
     */
    private function respaldoEsDeEsteSistema(string $sql): bool
    {
        $faltantes = $this->tablasFaltantes($sql);

        if ($faltantes !== []) {
            $this->error('El respaldo no parece ser de EstimaWeb.');
            $this->line('No se encontraron las tablas: ' . implode(', ', $faltantes));
            $this->line('Verifique que no sea el respaldo de otro proyecto.');

            return false;
        }

        $this->info('Respaldo verificado: contiene las tablas de EstimaWeb.');

        return true;
    }

    /**
     * Recorre el archivo completo buscando los CREATE TABLE.
     *
     * Leer solo los primeros bytes da falsos negativos: en un dump de varios MB
     * las tablas aparecen repartidas a lo largo de todo el archivo.
     *
     * @return list<string>
     */
    private function tablasFaltantes(string $sql): array
    {
        $pendientes = array_flip(self::TABLAS_ESPERADAS);
        $puntero = fopen($sql, 'rb');

        if ($puntero === false) {
            return self::TABLAS_ESPERADAS;
        }

        try {
            while ($pendientes !== [] && ($linea = fgets($puntero)) !== false) {
                foreach (array_keys($pendientes) as $tabla) {
                    if (str_contains($linea, "CREATE TABLE `{$tabla}`")) {
                        unset($pendientes[$tabla]);
                    }
                }
            }
        } finally {
            fclose($puntero);
        }

        return array_keys($pendientes);
    }

    private function importar(string $sql, string $base): int
    {
        $conexion = config('database.connections.mysql');

        DB::statement("DROP DATABASE IF EXISTS `{$base}`");
        DB::statement("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $this->info("Base '{$base}' recreada. Importando (puede tardar)...");

        $proceso = Process::fromShellCommandline(
            sprintf(
                '%s --host=%s --port=%s --user=%s %s %s < %s',
                $this->rutaCliente(),
                escapeshellarg((string) $conexion['host']),
                escapeshellarg((string) $conexion['port']),
                escapeshellarg((string) $conexion['username']),
                $conexion['password'] ? '--password=' . escapeshellarg((string) $conexion['password']) : '',
                escapeshellarg($base),
                escapeshellarg($sql)
            )
        );

        $proceso->setTimeout(1800);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            $this->error('Fallo la importacion:');
            $this->line($proceso->getErrorOutput());

            return self::FAILURE;
        }

        $this->mostrarResumen($base);

        return self::SUCCESS;
    }

    /**
     * Informa que entro, para detectar una carga incompleta de inmediato.
     */
    private function mostrarResumen(string $base): void
    {
        $tablas = DB::select(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ?',
            [$base]
        );

        $filas = [];

        foreach (['cotizaciones', 'item_cotizaciones', 'aius', 'users'] as $tabla) {
            try {
                $filas[] = [$tabla, DB::table("{$base}.{$tabla}")->count()];
            } catch (\Throwable) {
                $filas[] = [$tabla, 'no existe'];
            }
        }

        $this->newLine();
        $this->info("Carga completa en '{$base}': " . ($tablas[0]->total ?? 0) . ' tablas.');
        $this->table(['Tabla', 'Registros'], $filas);
        $this->newLine();
        $this->line('Los datos son una COPIA local. Produccion no fue modificada.');
    }

    /**
     * Ubica el cliente mysql del WAMP local, cuya version cambia entre equipos.
     */
    private function rutaCliente(): string
    {
        foreach (glob('C:/wamp64/bin/mysql/mysql*/bin/mysql.exe') ?: [] as $ruta) {
            return escapeshellarg($ruta);
        }

        return 'mysql';
    }
}
