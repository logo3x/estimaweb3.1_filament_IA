<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El repositorio tiene que bastarse solo para arrancar en el servidor.
 *
 * El hosting es cPanel SIN SSH: no hay terminal donde ejecutar
 * `composer install` ni `npm run build`. Desplegar es un `git pull`, asi que
 * si las librerias o los assets se quedan fuera del repositorio, el sitio
 * cae y no hay forma de repararlo desde alli.
 *
 * Es un fallo que no se nota al programar -en local todo esta- sino solo al
 * desplegar. De ahi estas comprobaciones.
 */
class DespliegueTest extends TestCase
{
    /** @return list<string> */
    private function gitignore(): array
    {
        $contenido = file_get_contents(base_path('.gitignore'));

        $this->assertNotFalse($contenido, 'No se pudo leer .gitignore.');

        return array_map('trim', explode("\n", $contenido));
    }

    /**
     * Lo que el servidor necesita y no se puede generar alli.
     *
     * @return array<string, array{string, string}>
     */
    public static function loQueDebeViajar(): array
    {
        return [
            'librerías' => ['/vendor', 'Sin vendor/ no arranca: en cPanel no hay composer install.'],
            'assets del sitio' => ['/public/build', 'Sin public/build/ Laravel lanza ViteException: alli no hay npm.'],
            'estilos de Filament' => ['/public/css/filament', 'Sin los estilos, el panel se ve en crudo.'],
            'scripts de Filament' => ['/public/js/filament', 'Sin los scripts, el panel no responde.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('loQueDebeViajar')]
    public function test_no_se_ignora_lo_que_el_servidor_necesita(string $ruta, string $porque): void
    {
        $this->assertNotContains(
            $ruta,
            $this->gitignore(),
            "{$ruta} está en .gitignore. {$porque}"
        );
    }

    /**
     * node_modules si se queda fuera.
     *
     * Solo hace falta para compilar los assets, y eso ocurre en local. Son
     * decenas de miles de archivos que no pintan nada en el servidor.
     */
    public function test_node_modules_no_viaja(): void
    {
        $this->assertContains('/node_modules', $this->gitignore());
    }

    /** Los archivos que de verdad tienen que estar, estan. */
    public function test_estan_las_librerias_y_los_assets(): void
    {
        foreach ([
            'vendor/autoload.php',
            'public/build/manifest.json',
            'public/css/filament',
            'public/js/filament',
        ] as $ruta) {
            $this->assertFileExists(
                base_path($ruta),
                "Falta {$ruta}. Ejecuta: php artisan desplegar:preparar"
            );
        }
    }

    /**
     * cPanel no despliega sin este archivo.
     *
     * Es el que le dice que copiar y donde. Sin el, el boton de desplegar
     * ni siquiera aparece en el panel.
     */
    public function test_existe_el_archivo_de_despliegue_de_cpanel(): void
    {
        $this->assertFileExists(base_path('.cpanel.yml'));
    }

    /**
     * El .cpanel.yml nunca debe pisar la configuracion ni los datos.
     *
     * Un despliegue que sobrescriba .env deja el sitio sin claves ni base de
     * datos; uno que sobrescriba storage/ borra los documentos generados y
     * los archivos que subieron los usuarios.
     */
    public function test_el_despliegue_respeta_la_configuracion_y_los_datos(): void
    {
        $yml = (string) file_get_contents(base_path('.cpanel.yml'));

        foreach (['.env', 'storage/'] as $intocable) {
            $this->assertStringContainsString(
                "--exclude='{$intocable}'",
                $yml,
                "El despliegue pisaría {$intocable} en el servidor."
            );
        }
    }

    /**
     * El despliegue no puede depender de herramientas que alli no existen.
     *
     * Si alguien añade un `composer install` a las tareas, el despliegue
     * fallara en el servidor y el fallo solo se vera en produccion.
     */
    public function test_el_despliegue_no_usa_composer_ni_npm(): void
    {
        $tareas = (string) file_get_contents(base_path('.cpanel.yml'));

        foreach (explode("\n", $tareas) as $linea) {
            if (str_starts_with(trim($linea), '#') || trim($linea) === '') {
                continue;
            }

            foreach (['composer ', 'npm '] as $prohibido) {
                $this->assertStringNotContainsString(
                    $prohibido,
                    $linea,
                    "El despliegue usa '{$prohibido}', que no existe en este hosting."
                );
            }
        }
    }
}
