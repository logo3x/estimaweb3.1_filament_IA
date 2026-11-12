<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Impide que vuelvan a entrar datos de clientes al repositorio.
 *
 * Los golden masters se generan desde cotizaciones reales, asi que arrastran
 * los nombres de los servicios contratados: "Deteccion de fugas de gas
 * natural Estacion ERCT 2" dice a que se dedica el cliente y cuanto paga.
 * Eso estuvo publicado en un repositorio publico.
 *
 * Las cifras SI se conservan -- son la referencia del calculo y sin ellas los
 * snapshots no sirven --, pero los textos identificables se reemplazan con
 * `php artisan golden:anonimizar` antes de versionarlos.
 */
class DatosSensiblesTest extends TestCase
{
    /**
     * Clientes conocidos del sistema.
     *
     * No es una lista exhaustiva: es una red que atrapa los casos reales que
     * ya estuvieron expuestos.
     *
     * @var list<string>
     */
    private const CLIENTES = [
        'ecopetrol', 'frontera', 'parex', 'perenco', 'canacol',
        'drummond', 'gran tierra', 'hocol', 'petrobras', 'odl',
        'oleoducto', 'transmetano', 'agrocascada', 'emerald',
    ];

    /** @return list<string> */
    private function snapshots(): array
    {
        return glob(__DIR__ . '/../golden/cotizacion-*.json') ?: [];
    }

    /** Ningun snapshot menciona a un cliente. */
    public function test_los_snapshots_no_mencionan_clientes(): void
    {
        $encontrados = [];

        foreach ($this->snapshots() as $archivo) {
            $contenido = mb_strtolower((string) file_get_contents($archivo));

            foreach (self::CLIENTES as $cliente) {
                if (str_contains($contenido, $cliente)) {
                    $encontrados[] = basename($archivo) . " menciona '{$cliente}'";
                }
            }
        }

        $this->assertSame(
            [],
            $encontrados,
            "Hay datos de clientes en los golden masters:\n" . implode("\n", $encontrados)
                . "\n\nEjecute: php artisan golden:anonimizar"
        );
    }

    /**
     * Los nombres de item son etiquetas genericas.
     *
     * Un nombre de servicio describe el negocio del cliente aunque no lo
     * nombre: "Mantenimiento de valvulas en estacion de bombeo" ya dice
     * bastante.
     */
    public function test_los_nombres_de_item_son_genericos(): void
    {
        $concretos = [];

        foreach ($this->snapshots() as $archivo) {
            $datos = json_decode((string) file_get_contents($archivo), true);

            foreach ($datos['items'] ?? [] as $item) {
                $nombre = (string) ($item['nombre'] ?? '');

                if ($nombre !== '' && ! str_starts_with($nombre, 'Item ')) {
                    $concretos[] = basename($archivo) . ": '{$nombre}'";
                }
            }
        }

        $this->assertSame(
            [],
            array_slice($concretos, 0, 10),
            'Hay nombres de servicio sin anonimizar. Ejecute: php artisan golden:anonimizar'
        );
    }

    /**
     * Las cifras siguen presentes.
     *
     * Anonimizar no puede vaciar los snapshots: sin totales ni valores
     * unitarios dejan de servir como referencia y las pruebas pasarian
     * comparando nada contra nada.
     */
    public function test_los_snapshots_conservan_sus_cifras(): void
    {
        $conTotales = 0;

        foreach ($this->snapshots() as $archivo) {
            $datos = json_decode((string) file_get_contents($archivo), true);

            $this->assertArrayHasKey('totales', $datos, basename($archivo) . ' perdio sus totales.');
            $this->assertArrayHasKey('items', $datos, basename($archivo) . ' perdio sus items.');

            if ((float) ($datos['totales']['con_iva'] ?? 0) > 0) {
                $conTotales++;
            }
        }

        $this->assertGreaterThan(
            30,
            $conTotales,
            'Quedan muy pocos snapshots con cifras: la referencia del calculo se perdio.'
        );
    }

    /** Los respaldos de la base nunca se versionan. */
    public function test_los_respaldos_estan_excluidos_de_git(): void
    {
        $gitignore = (string) file_get_contents(base_path('.gitignore'));

        foreach (['.env', '.env.testing', '/storage/app/respaldos', '*.sql'] as $patron) {
            $this->assertStringContainsString(
                $patron,
                $gitignore,
                "El patron '{$patron}' deberia estar en .gitignore."
            );
        }
    }
}
