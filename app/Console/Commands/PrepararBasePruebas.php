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
     * Bases que este comando no puede recrear bajo ninguna circunstancia.
     *
     * @var list<string>
     */
    private const BASES_PROTEGIDAS = ['estimaweb31', 'cotizaciones'];

    /**
     * Orden de insercion: cada tabla despues de aquellas a las que referencia.
     *
     * @var list<string>
     */
    private const ORDEN = [
        'gerencias', 'users', 'turnos', 'epps', 'examenes_vacunas', 'origen_factors',
        /**
         * Roles y permisos.
         *
         * Sin ellos, los 21 usuarios existen pero ninguno puede entrar al
         * panel, y cualquier prueba de pantalla falla antes de empezar.
         */
        'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        'datos_herramientas', 'datos_equipos', 'datos_consumibles', 'datos_comunicaciones',
        'precargado_epps', 'precargado_heramientas', 'precargado_equipos', 'precargado_consumibles',
        'precargado_item_epps', 'precargado_item_heramientas',
        'precargado_item_equipos', 'precargado_item_consumibles',
        'cotizaciones', 'aius', 'item_cotizaciones',
        'personals', 'per_aux_otros', 'per_aux_convencionales',
        'personal_epps', 'personal_examenes_vacunas',
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

        if (in_array($destino, self::BASES_PROTEGIDAS, true)) {
            $this->error("'{$destino}' es la base de referencia: no se puede recrear.");
            $this->line('Si se borro por accidente, recuperela con: php artisan datos:cargar');

            return self::FAILURE;
        }

        $this->info("Recreando el esquema de '{$destino}' desde las migraciones...");

        /**
         * La conexion se apunta a la base destino ANTES de migrar.
         *
         * Pasar --env a $this->call() no sirve: el entorno ya quedo cargado al
         * arrancar el proceso, asi que la opcion se ignora y migrate:fresh
         * acaba borrando la base de la conexion actual. Asi se borro una vez
         * la copia de referencia entera.
         */
        config(['database.connections.mysql.database' => $destino]);
        DB::purge('mysql');

        $this->call('migrate:fresh', ['--force' => true]);

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
                /**
                 * Se consulta con SQL directo y no con DB::table("base.tabla"):
                 * el query builder escapa el nombre completo entre comillas,
                 * punto incluido, y acaba buscando una tabla que no existe.
                 */
                $esperadas = (int) DB::selectOne(
                    "SELECT COUNT(*) AS n FROM `{$origen}`.`{$tabla}`"
                )->n;

                DB::statement("DELETE FROM `{$destino}`.`{$tabla}`");

                /** Solo las columnas presentes en el esquema nuevo. */
                $columnas = array_map(
                    static fn (object $c): string => $c->Field,
                    DB::select("SHOW COLUMNS FROM `{$destino}`.`{$tabla}`")
                );

                $lista = '`' . implode('`,`', $columnas) . '`';

                DB::statement(
                    "INSERT INTO `{$destino}`.`{$tabla}` ({$lista}) SELECT {$lista} FROM `{$origen}`.`{$tabla}`"
                );

                $copiadas = (int) DB::selectOne(
                    "SELECT COUNT(*) AS n FROM `{$destino}`.`{$tabla}`"
                )->n;

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
