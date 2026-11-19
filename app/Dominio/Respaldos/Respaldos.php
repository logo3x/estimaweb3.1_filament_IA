<?php

namespace App\Dominio\Respaldos;

use Illuminate\Support\Collection;

/**
 * Crea, lista y limpia los respaldos de la base.
 *
 * Un respaldo es un .sql comprimido con gzip en `storage/app/respaldos/`.
 * Se guarda **fuera de public/**: contiene todos los precios, margenes y
 * datos de clientes, de modo que nadie debe poder descargarlo escribiendo
 * su direccion.
 */
class Respaldos
{
    /** Cuantos se conservan. Al pasarse, se borra el mas viejo. */
    public const CUANTOS_SE_GUARDAN = 10;

    public function __construct(private readonly VolcadoDeLaBase $volcado = new VolcadoDeLaBase()) {}

    /**
     * Genera un respaldo nuevo.
     *
     * @return array{archivo: string, tablas: int, filas: int, bytes: int}
     */
    public function crear(): array
    {
        $carpeta = $this->carpeta();

        $nombre = 'estimaweb-' . now()->format('Y-m-d_His') . '.sql.gz';
        $temporal = $carpeta . '/.' . $nombre . '.tmp';
        $definitivo = $carpeta . '/' . $nombre;

        /**
         * Se escribe a un temporal y se renombra al final.
         *
         * Si el proceso muere a mitad -en un hosting compartido pasa- el
         * archivo incompleto no queda con nombre de respaldo valido, que
         * seria peor que no tener ninguno: daria confianza falsa.
         */
        $resumen = $this->volcado->escribirEn($temporal);

        $this->comprimir($temporal, $definitivo);

        @unlink($temporal);

        $this->limpiarViejos();

        return [
            'archivo' => $nombre,
            'tablas' => $resumen['tablas'],
            'filas' => $resumen['filas'],
            'bytes' => filesize($definitivo) ?: 0,
        ];
    }

    /**
     * Los respaldos que hay, del mas nuevo al mas viejo.
     *
     * @return Collection<int, array{nombre: string, bytes: int, cuando: \Illuminate\Support\Carbon}>
     */
    public function listar(): Collection
    {
        $archivos = glob($this->carpeta() . '/*.sql.gz') ?: [];

        return collect($archivos)
            ->map(fn (string $ruta): array => [
                'nombre' => basename($ruta),
                'bytes' => filesize($ruta) ?: 0,
                'cuando' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($ruta) ?: 0),
            ])
            ->sortByDesc('cuando')
            ->values();
    }

    /**
     * La ruta completa de un respaldo, si existe y es uno de verdad.
     *
     * El nombre viene de la pantalla, asi que se valida contra la lista real
     * en vez de confiar en el: una ruta recibida de fuera nunca se usa tal
     * cual para abrir un archivo.
     */
    public function rutaDe(string $nombre): ?string
    {
        if (! $this->listar()->contains('nombre', $nombre)) {
            return null;
        }

        $ruta = $this->carpeta() . '/' . $nombre;

        return is_file($ruta) ? $ruta : null;
    }

    public function borrar(string $nombre): bool
    {
        $ruta = $this->rutaDe($nombre);

        return $ruta !== null && @unlink($ruta);
    }

    /** Cuando se hizo el ultimo, para avisar si hace mucho. */
    public function ultimo(): ?\Illuminate\Support\Carbon
    {
        return $this->listar()->first()['cuando'] ?? null;
    }

    /** Comprime el .sql y descarta el original. */
    private function comprimir(string $origen, string $destino): void
    {
        $entrada = fopen($origen, 'rb');
        $salida = gzopen($destino, 'wb9');

        if ($entrada === false || $salida === false) {
            throw new \RuntimeException('No se pudo comprimir el respaldo.');
        }

        try {
            while (! feof($entrada)) {
                gzwrite($salida, fread($entrada, 262144) ?: '');
            }
        } finally {
            fclose($entrada);
            gzclose($salida);
        }
    }

    /** Deja solo los mas recientes. */
    private function limpiarViejos(): void
    {
        $this->listar()
            ->slice(self::CUANTOS_SE_GUARDAN)
            ->each(fn (array $respaldo) => @unlink($this->carpeta() . '/' . $respaldo['nombre']));
    }

    private function carpeta(): string
    {
        $carpeta = storage_path('app/respaldos');

        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }

        return $carpeta;
    }
}
