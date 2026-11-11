<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Impide que un comando destructivo arrase la base de referencia.
 *
 * La base de trabajo guarda la copia de los datos reales de produccion contra
 * la que se comparan ambas versiones del sistema. Un `migrate:fresh` lanzado
 * sin pensar la borra entera y deja la comparacion sin su punto de partida
 * (paso: recuperarla cuesta recargar el respaldo completo).
 *
 * Aqui se bloquean esos comandos salvo que apunten a la base de pruebas, que
 * si es desechable y se recrea en cada corrida. Para forzarlo de todos modos
 * existe la opcion --realmente-si, que obliga a escribirlo a proposito.
 */
class ProteccionBaseReferenciaServiceProvider extends ServiceProvider
{
    /** Comandos que borran o reescriben tablas enteras. */
    private const COMANDOS_DESTRUCTIVOS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    /** Bases que si pueden borrarse sin aviso. */
    private const BASES_DESECHABLES = [
        'estimaweb31_test',
    ];

    public function boot(): void
    {
        Event::listen(function (CommandStarting $evento): void {
            if (! in_array((string) $evento->command, self::COMANDOS_DESTRUCTIVOS, true)) {
                return;
            }

            if ($evento->input->hasParameterOption('--realmente-si')) {
                return;
            }

            $base = (string) config('database.connections.' . config('database.default') . '.database');

            if (in_array($base, self::BASES_DESECHABLES, true)) {
                return;
            }

            throw new RuntimeException(
                "Bloqueado: '{$evento->command}' borraria la base '{$base}', que guarda la copia "
                . 'de los datos de produccion usada como referencia.' . PHP_EOL
                . 'Para trabajar sobre la base desechable: --env=testing' . PHP_EOL
                . 'Para recuperar la referencia si ya se borro: php artisan datos:cargar' . PHP_EOL
                . 'Para hacerlo de todos modos: agregue --realmente-si'
            );
        });
    }
}
