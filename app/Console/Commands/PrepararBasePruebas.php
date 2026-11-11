<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Deja la base de pruebas con el esquema de las migraciones y los datos reales.
 *
 * Las pruebas de este proyecto comparan contra cotizaciones de produccion, asi
 * que no sirven datos inventados: hace falta el esquema nuevo lleno con los
 * datos viejos. Este comando copia fila por fila desde la base de referencia.
 *
 * Es tambien la verificacion mas dura del esquema: si una columna quedo
 * demasiado estrecha o con un NOT NULL de mas, la copia falla aqui y no en
 * produccion.
 */
class PrepararBasePruebas extends Command
{
    protected $signature = 'pruebas:preparar
        {--origen=estimaweb31 : Base con la copia de los datos de produccion}
        {--destino=estimaweb31_test : Base de pruebas a reconstruir}';

    protected $description = 'Recrea la base de pruebas con el esquema nuevo y los datos reales';

    /**
     * Orden de insercion: cada tabla despues de aquellas a las que referencia.
     *
     * @var list<string>
     */
    private const ORDEN = [
        'gerencias', 'users', 'turnos', 'epps', 'examenes_vacunas',
        'datos_herramientas', 'datos_equipos', 'datos_consumibles', 'datos_comunicaciones',
        'cotizaciones', 'aius', 'item_cotizaciones',
        'personals', 'per_aux_otros', 'personal_epps', 'personal_examenes_vacunas',
        'herramientas', 'equipos', 'consumibles',
        'item_herramientas', 'item_equipos', 'item_consumibles',
        'vehiculos', 'oficinas', 'aseos', 'viaticos', 'servicios', 'otros', 'comunicaciones',
        'revisions', 'impresion_registros',
    ];

    public function handle(): int
    {
        $origen = (string) $this->option('origen');
        $destino = (string) $this->option('destino');

        if ($destino === $origen) {
            $this->error('El destino no puede ser la base de referencia: se borraria.');

            return self::FAILURE;
        }

        $this->info("Recreando el esquema de '{$destino}' desde las migraciones...");

        $this->call('migrate:fresh', ['--force' => true, '--env' => 'testing']);

        $this->newLine();
        $this->info("Copiando datos reales desde '{$origen}'...");

        try {
            $filas = $this->copiarDatos($origen, $destino);
        } catch (Throwable $e) {
            $this->error('Fallo la copia: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Base de pruebas lista: ' . count(self::ORDEN) . " tablas, {$filas} filas.");

        return self::SUCCESS;
    }

    /**
     * Copia las tablas en orden, exigiendo que no se pierda ninguna fila.
     *
     * @throws RuntimeException si una tabla llega incompleta
     */
    private function copiarDatos(string $origen, string $destino): int
    {
        $total = 0;

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach (self::ORDEN as $tabla) {
                $esperadas = DB::table("{$origen}.{$tabla}")->count();

                DB::table("{$destino}.{$tabla}")->delete();

                /** Solo las columnas presentes en el esquema nuevo. */
                $columnas = array_map(
                    static fn (object $c): string => $c->Field,
                    DB::select("SHOW COLUMNS FROM `{$destino}`.`{$tabla}`")
                );

                $lista = '`' . implode('`,`', $columnas) . '`';

                DB::statement(
                    "INSERT INTO `{$destino}`.`{$tabla}` ({$lista}) SELECT {$lista} FROM `{$origen}`.`{$tabla}`"
                );

                $copiadas = DB::table("{$destino}.{$tabla}")->count();

                if ($copiadas !== $esperadas) {
                    throw new RuntimeException(
                        "{$tabla}: se esperaban {$esperadas} filas y llegaron {$copiadas}."
                    );
                }

                $total += $copiadas;
                $this->line(sprintf('  %-32s %5d filas', $tabla, $copiadas));
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $total;
    }
}
