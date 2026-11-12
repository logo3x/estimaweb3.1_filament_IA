<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Comprueba la configuracion de seguridad antes de un despliegue.
 *
 * El hosting de destino es cPanel sin acceso SSH, asi que no habra ocasion de
 * revisar esto a mano desde una consola. Este comando se puede ejecutar desde
 * el modulo de mantenimiento y responde a una pregunta concreta: esta el
 * sistema listo para recibir usuarios reales.
 *
 * APP_DEBUG=true en produccion es el caso mas grave: ante cualquier error,
 * Laravel muestra en pantalla la traza completa, fragmentos de codigo y los
 * valores de configuracion, incluidas credenciales.
 */
class VerificarSeguridad extends Command
{
    protected $signature = 'seguridad:verificar
        {--produccion : Exigir tambien lo que solo aplica a un servidor real}';

    protected $description = 'Revisa la configuracion de seguridad y avisa de lo que falta';

    /** @var list<array{string, string}> */
    private array $fallos = [];

    /** @var list<array{string, string}> */
    private array $avisos = [];

    public function handle(): int
    {
        $exigirProduccion = (bool) $this->option('produccion') || app()->environment('production');

        $this->revisarDepuracion($exigirProduccion);
        $this->revisarClaveDeAplicacion();
        $this->revisarSesion($exigirProduccion);
        $this->revisarBaseDeDatos($exigirProduccion);

        return $this->informar($exigirProduccion);
    }

    private function revisarDepuracion(bool $produccion): void
    {
        if (! config('app.debug')) {
            return;
        }

        $mensaje = 'APP_DEBUG expone la traza completa y la configuracion ante cualquier error.';

        $produccion
            ? $this->fallos[] = ['APP_DEBUG activo', $mensaje]
            : $this->avisos[] = ['APP_DEBUG activo', 'Correcto en desarrollo. ' . $mensaje];
    }

    private function revisarClaveDeAplicacion(): void
    {
        $clave = (string) config('app.key');

        if ($clave === '') {
            $this->fallos[] = ['APP_KEY vacia', 'Sin ella no se pueden cifrar cookies ni sesiones.'];

            return;
        }

        if (! str_starts_with($clave, 'base64:') || strlen($clave) < 40) {
            $this->fallos[] = ['APP_KEY invalida', 'Generela con: php artisan key:generate'];
        }
    }

    private function revisarSesion(bool $produccion): void
    {
        $vida = (int) config('session.lifetime');

        if ($vida > 60) {
            $this->avisos[] = [
                "Sesion de {$vida} minutos",
                'Para datos financieros conviene entre 15 y 30 minutos.',
            ];
        }

        if (! config('session.http_only')) {
            $this->fallos[] = ['Cookie accesible por JavaScript', 'Active http_only en config/session.php.'];
        }

        if (config('session.same_site') === null) {
            $this->fallos[] = ['same_site sin definir', 'Use "lax" o "strict" para limitar peticiones externas.'];
        }

        if ($produccion && config('session.secure') !== true) {
            $this->fallos[] = [
                'Cookie sin exigir HTTPS',
                'Defina SESSION_SECURE_COOKIE=true: sin ello la sesion viaja en claro sobre http.',
            ];
        }
    }

    private function revisarBaseDeDatos(bool $produccion): void
    {
        if (! $produccion) {
            return;
        }

        $usuario = (string) config('database.connections.' . config('database.default') . '.username');

        if (in_array(strtolower($usuario), ['root', 'admin'], true)) {
            $this->fallos[] = [
                "Base de datos con el usuario '{$usuario}'",
                'Use una cuenta restringida a esta base, sin permisos de administrador.',
            ];
        }

        if ((string) config('database.connections.' . config('database.default') . '.password') === '') {
            $this->fallos[] = ['Base de datos sin contrasena', 'Asigne una contrasena al usuario de la base.'];
        }
    }

    private function informar(bool $produccion): int
    {
        $this->newLine();
        $this->line('Revision de seguridad (' . ($produccion ? 'criterio de produccion' : 'criterio de desarrollo') . ')');
        $this->newLine();

        foreach ($this->fallos as [$titulo, $detalle]) {
            $this->line("  <fg=red>FALLA</>  {$titulo}");
            $this->line("         {$detalle}");
        }

        foreach ($this->avisos as [$titulo, $detalle]) {
            $this->line("  <fg=yellow>AVISO</>  {$titulo}");
            $this->line("         {$detalle}");
        }

        $this->newLine();

        if ($this->fallos !== []) {
            $this->error(count($this->fallos) . ' problema(s) que impiden un despliegue seguro.');

            return self::FAILURE;
        }

        $this->info('Sin problemas criticos.' . ($this->avisos !== [] ? ' Revise los avisos.' : ''));

        return self::SUCCESS;
    }
}
