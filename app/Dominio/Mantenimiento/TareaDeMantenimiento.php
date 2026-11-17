<?php

namespace App\Dominio\Mantenimiento;

/**
 * Lo que hay que hacer en el servidor despues de desplegar.
 *
 * El hosting es cPanel **sin consola**: no hay forma de escribir
 * `php artisan` alli. Estas son las tareas que de otro modo habria que
 * ejecutar a mano, expuestas para dispararlas desde el panel.
 *
 * Cada una dice ademas **cuando hace falta**, porque quien las ejecuta no
 * tiene por que saberlo de memoria.
 */
enum TareaDeMantenimiento: string
{
    /** Tras cada `git pull`: el codigo cambio y las caches apuntan al viejo. */
    case RehacerCaches = 'rehacer_caches';

    /** Cuando el despliegue trae tablas o columnas nuevas. */
    case AplicarMigraciones = 'aplicar_migraciones';

    /** Una vez, al montar el sitio: los archivos subidos no se ven sin esto. */
    case EnlazarArchivos = 'enlazar_archivos';

    /** Cuando algo se ve raro y se sospecha de una cache vieja. */
    case LimpiarCaches = 'limpiar_caches';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RehacerCaches => 'Rehacer las cachés',
            self::AplicarMigraciones => 'Aplicar migraciones pendientes',
            self::EnlazarArchivos => 'Enlazar los archivos subidos',
            self::LimpiarCaches => 'Limpiar las cachés',
        };
    }

    /** Cuando hace falta ejecutarla. */
    public function cuandoHaceFalta(): string
    {
        return match ($this) {
            self::RehacerCaches => 'Después de cada despliegue. Deja el sitio '
                . 'leyendo el código nuevo y lo acelera.',
            self::AplicarMigraciones => 'Cuando el despliegue trae tablas o '
                . 'columnas nuevas. Si no hay ninguna pendiente, no hace nada.',
            self::EnlazarArchivos => 'Una sola vez, al montar el sitio. Sin '
                . 'esto los archivos subidos no se ven.',
            self::LimpiarCaches => 'Cuando algo se ve raro y se sospecha que '
                . 'quedó una caché vieja. Deja el sitio más lento hasta rehacerlas.',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::RehacerCaches => 'heroicon-o-bolt',
            self::AplicarMigraciones => 'heroicon-o-circle-stack',
            self::EnlazarArchivos => 'heroicon-o-link',
            self::LimpiarCaches => 'heroicon-o-trash',
        };
    }

    /**
     * Si conviene preguntar antes.
     *
     * Las migraciones cambian la estructura de la base y no se deshacen
     * solas; limpiar las caches deja el sitio lento un rato.
     */
    public function pideConfirmacion(): bool
    {
        return match ($this) {
            self::AplicarMigraciones, self::LimpiarCaches => true,
            default => false,
        };
    }

    /**
     * Los comandos que ejecuta, en orden.
     *
     * Se declaran aqui y no en la pantalla para que la lista sea revisable
     * de un vistazo: es lo que se va a correr en el servidor.
     *
     * @return array<int, array{string, array<string, mixed>}>
     */
    public function comandos(): array
    {
        return match ($this) {
            self::RehacerCaches => [
                ['optimize:clear', []],
                ['config:cache', []],
                ['route:cache', []],
                ['view:cache', []],
            ],
            self::AplicarMigraciones => [
                /** `--force` porque en produccion Laravel pregunta y aqui nadie responde. */
                ['migrate', ['--force' => true]],
            ],
            self::EnlazarArchivos => [
                ['storage:link', []],
            ],
            self::LimpiarCaches => [
                ['optimize:clear', []],
            ],
        };
    }
}
