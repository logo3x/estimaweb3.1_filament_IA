<?php

namespace App\Console\Commands;

use App\Dominio\Respaldos\Respaldos;
use Illuminate\Console\Command;

/**
 * Crea un respaldo de la base.
 *
 * Pensado para el cron de cPanel, que es la unica forma de ejecutar algo
 * periodico sin SSH. Tambien sirve a mano desde la pantalla de respaldos.
 */
class CrearRespaldo extends Command
{
    protected $signature = 'respaldo:crear';

    protected $description = 'Genera un respaldo comprimido de la base de datos';

    public function handle(Respaldos $respaldos): int
    {
        try {
            $resumen = $respaldos->crear();
        } catch (\Throwable $e) {
            /**
             * Se informa y se devuelve fallo para que el cron lo note.
             *
             * Un respaldo que falla en silencio es peor que no tenerlo: se
             * confia en el hasta el dia que hace falta.
             */
            $this->components->error('No se pudo crear el respaldo: ' . $e->getMessage());

            report($e);

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Respaldo creado: %s (%s tablas, %s filas, %s)',
            $resumen['archivo'],
            number_format($resumen['tablas'], 0, ',', '.'),
            number_format($resumen['filas'], 0, ',', '.'),
            $this->enPalabras($resumen['bytes']),
        ));

        $this->line('  Se conservan los ' . Respaldos::CUANTOS_SE_GUARDAN . ' mas recientes.');

        /**
         * De paso se limpian las mediciones viejas.
         *
         * Va aqui porque el cron de cPanel ya ejecuta este comando a
         * diario: en el sistema anterior la purga existia como comando
         * propio pero no estaba programada, asi que no corria nunca y la
         * tabla crecia sin limite.
         */
        $this->purgarMediciones();

        return self::SUCCESS;
    }

    /** Borra las mediciones de rendimiento mas viejas de lo util. */
    private function purgarMediciones(): void
    {
        try {
            $borradas = app(\App\Dominio\Rendimiento\Rendimiento::class)->purgar();
        } catch (\Throwable $e) {
            /** Que falle la limpieza no puede invalidar el respaldo. */
            $this->components->warn('No se pudieron limpiar las mediciones: ' . $e->getMessage());

            return;
        }

        if ($borradas > 0) {
            $this->line("  Se borraron {$borradas} mediciones de rendimiento viejas.");
        }
    }

    private function enPalabras(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
