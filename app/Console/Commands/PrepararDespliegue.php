<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Deja el proyecto listo para subir al servidor.
 *
 * El hosting es cPanel SIN SSH: alli no hay terminal para ejecutar
 * `composer install` ni `npm run build`. Desplegar consiste en un `git pull`
 * desde el panel, de modo que **todo lo que la aplicacion necesita para
 * arrancar tiene que estar ya dentro del repositorio**: las librerias
 * (`vendor/`), los assets compilados (`public/build/`) y los de Filament.
 *
 * Este comando los regenera en el estado correcto y avisa de lo que falte.
 * Se ejecuta aqui, antes de hacer commit; nunca en el servidor.
 *
 * Deja `vendor/` **sin las librerias de desarrollo**: phpunit, faker y
 * compañia pesan 19 MB y no pintan nada en produccion. Por eso, al terminar,
 * las pruebas no se pueden ejecutar hasta restaurarlas con un
 * `composer install` normal, cosa que el propio comando recuerda.
 */
class PrepararDespliegue extends Command
{
    protected $signature = 'desplegar:preparar
        {--revisar : Solo comprueba, no regenera nada}';

    protected $description = 'Regenera librerías y assets para que el despliegue por git pull funcione';

    public function handle(): int
    {
        $this->components->info('Preparando el proyecto para subirlo al servidor.');

        if ($this->option('revisar')) {
            return $this->revisar();
        }

        foreach ($this->pasos() as $titulo => $paso) {
            $this->components->task($titulo, $paso);
        }

        $this->newLine();

        return $this->revisar();
    }

    /**
     * Los pasos, en orden.
     *
     * Las dependencias de Node van primero porque compilar los assets las
     * necesita; `vendor/` se recorta al final para que los scripts de
     * Composer -que publican los assets de Filament- hayan corrido antes.
     *
     * @return array<string, callable(): bool>
     */
    private function pasos(): array
    {
        return [
            'Instalando dependencias de Node' => fn (): bool => $this->ejecutar('npm install --ignore-scripts'),
            'Compilando los assets del sitio' => fn (): bool => $this->ejecutar('npm run build'),
            'Publicando los assets de Filament' => fn (): bool => $this->artisan('filament:assets'),
            'Dejando vendor/ sin librerías de desarrollo' => fn (): bool => $this->ejecutar(
                'composer install --no-dev --optimize-autoloader --no-interaction'
            ),
        ];
    }

    /** Comprueba que esta todo lo que el servidor necesita. */
    private function revisar(): int
    {
        $faltan = [];

        foreach ($this->loImprescindible() as $ruta => $queEs) {
            $existe = file_exists(base_path($ruta));

            $this->components->twoColumnDetail(
                $queEs,
                $existe ? '<fg=green>listo</>' : '<fg=red>FALTA</>'
            );

            if (! $existe) {
                $faltan[] = $ruta;
            }
        }

        $this->newLine();

        if ($faltan !== []) {
            $this->components->error(
                'Falta ' . implode(', ', $faltan) . '. Ejecuta el comando sin --revisar.'
            );

            return self::FAILURE;
        }

        return $this->avisarDeLoQueNoSePuedeAutomatizar();
    }

    /**
     * Lo que tiene que existir si o si.
     *
     * @return array<string, string>
     */
    private function loImprescindible(): array
    {
        return [
            'vendor/autoload.php' => 'Librerías (vendor/)',
            'public/build/manifest.json' => 'Assets del sitio (public/build/)',
            'public/css/filament' => 'Estilos de Filament',
            'public/js/filament' => 'Scripts de Filament',
        ];
    }

    /** Lo que depende de una persona, no del comando. */
    private function avisarDeLoQueNoSePuedeAutomatizar(): int
    {
        $sinDesarrollo = ! is_dir(base_path('vendor/phpunit'));

        $this->components->info('Todo lo que el servidor necesita está en su sitio.');

        $this->components->bulletList(array_filter([
            $sinDesarrollo
                ? 'vendor/ está sin librerías de desarrollo: correcto para subir.'
                : 'OJO: vendor/ todavía trae phpunit y compañía. Vuelve a ejecutar el comando.',
            'Para volver a ejecutar las pruebas aquí: composer install',
            'La plantilla de la oferta NO viaja en el repositorio: vive en el servidor.',
            'Revisa que .env del servidor tenga APP_DEBUG=false y la base correcta.',
        ]));

        return $sinDesarrollo ? self::SUCCESS : self::FAILURE;
    }

    private function ejecutar(string $comando): bool
    {
        return Process::path(base_path())
            ->timeout(600)
            ->run($comando)
            ->successful();
    }

    private function artisan(string $comando): bool
    {
        return $this->callSilently($comando) === self::SUCCESS;
    }
}
