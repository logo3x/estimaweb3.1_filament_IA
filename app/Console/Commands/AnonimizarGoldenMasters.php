<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Quita de los golden masters los textos que identifican a un cliente.
 *
 * Los snapshots son la referencia del calculo y deben conservar cada cifra
 * intacta, pero no necesitan los nombres de los servicios: "Deteccion de
 * fugas de gas natural Estacion ERCT 2" revela a que se dedica el cliente y
 * cuanto paga por ello.
 *
 * Lo que se conserva:  totales, valores unitarios, porcentajes del AIU,
 *                      cantidades, unidades, ids y numeros de item.
 * Lo que se reemplaza: unicamente el nombre de cada item.
 *
 * Los numeros no se tocan: anonimizarlos invalidaria la referencia, que es
 * justo lo que da valor a estos archivos.
 */
class AnonimizarGoldenMasters extends Command
{
    protected $signature = 'golden:anonimizar
        {--carpeta= : Carpeta de los snapshots}
        {--dry-run : Solo informar, sin escribir}';

    protected $description = 'Reemplaza los nombres de servicio de los golden masters por etiquetas genericas';

    public function handle(): int
    {
        $carpeta = (string) ($this->option('carpeta') ?: base_path('tests/golden'));

        $archivos = glob($carpeta . '/cotizacion-*.json') ?: [];

        if ($archivos === []) {
            $this->error("No hay snapshots en {$carpeta}.");

            return self::FAILURE;
        }

        $simulacion = (bool) $this->option('dry-run');
        $itemsTocados = 0;

        foreach ($archivos as $archivo) {
            $datos = json_decode((string) file_get_contents($archivo), true);

            if (! is_array($datos) || ! isset($datos['items'])) {
                continue;
            }

            foreach ($datos['items'] as $i => $item) {
                if (! $this->identificaUnCliente($item['nombre'] ?? '')) {
                    continue;
                }

                $datos['items'][$i]['nombre'] = 'Item ' . ($item['numero'] ?? $i + 1);
                $itemsTocados++;
            }

            if (! $simulacion) {
                file_put_contents(
                    $archivo,
                    json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
        }

        $verbo = $simulacion ? 'se anonimizarian' : 'anonimizados';

        $this->info(count($archivos) . " snapshots revisados: {$itemsTocados} nombres {$verbo}.");

        if (! $simulacion) {
            $this->line('Las cifras no se tocaron: la referencia del calculo sigue siendo valida.');
        }

        return self::SUCCESS;
    }

    /**
     * Si el texto es un nombre real y no una etiqueta ya generica.
     *
     * Evita renombrar dos veces y deja constancia de cuantos quedaban.
     */
    private function identificaUnCliente(string $nombre): bool
    {
        return $nombre !== '' && ! str_starts_with($nombre, 'Item ');
    }
}
