<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Crea una cuenta por rol para probar el panel en local.
 *
 * Las cuentas reales tienen las contraseñas de produccion, que nadie conoce
 * aqui, y cambiarlas para probar seria ensuciar datos de personas de verdad.
 * Estas son cuentas aparte, con una contraseña conocida y un correo que no
 * existe, para poder entrar como cada rol y ver que cambia.
 *
 * **Solo funciona fuera de produccion.** Un comando que crea usuarios con
 * contraseña conocida no puede existir en el servidor.
 */
class CuentasDePrueba extends Command
{
    protected $signature = 'cuentas:prueba
        {--clave=estimaweb : La contraseña que tendran todas}
        {--borrar : Las elimina en vez de crearlas}';

    protected $description = 'Crea una cuenta por rol para probar el panel en local';

    /**
     * El dominio que las marca como de prueba.
     *
     * No es un dominio real: ningun correo puede salir hacia el.
     */
    private const DOMINIO = '@prueba.local';

    /**
     * Una cuenta por rol, mas los dos casos que de verdad dan problemas.
     *
     * La gerencia importa: cada quien ve solo las cotizaciones de la suya, asi
     * que dos cuentas en gerencias distintas enseñan el alcance mejor que
     * cualquier explicacion.
     *
     * @var list<array{correo: string, nombre: string, roles: list<string>, gerencia: int|null}>
     */
    private const CUENTAS = [
        [
            'correo' => 'superadmin',
            'nombre' => 'Super Admin',
            'roles' => ['super_admin'],
            'gerencia' => 4,
        ],
        [
            'correo' => 'admin',
            'nombre' => 'Administrador',
            'roles' => ['Administrador'],
            'gerencia' => 4,
        ],
        [
            'correo' => 'gerente',
            'nombre' => 'Gerente Zona 1',
            'roles' => ['Gerente'],
            'gerencia' => 4,
        ],
        [
            'correo' => 'director',
            'nombre' => 'Director Zona 1',
            'roles' => ['Director'],
            'gerencia' => 4,
        ],
        [
            'correo' => 'creador',
            'nombre' => 'Creador Zona 1',
            'roles' => ['Creador'],
            'gerencia' => 4,
        ],
        /** Otra gerencia: para ver que NO alcanza las cotizaciones de la Zona 1. */
        [
            'correo' => 'creador.zona3',
            'nombre' => 'Creador Zona 3',
            'roles' => ['Creador'],
            'gerencia' => 6,
        ],
        /**
         * Varios roles a la vez.
         *
         * No es un caso raro: seis personas reales acumulan roles y una llega
         * a cuatro. Los roles SUMAN, asi que esta cuenta puede lo de todas.
         */
        [
            'correo' => 'multirol',
            'nombre' => 'Creador y Gerente',
            'roles' => ['Creador', 'Gerente'],
            'gerencia' => 4,
        ],
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('En producción no: crearía usuarios con una contraseña conocida.');

            return self::FAILURE;
        }

        return $this->option('borrar') ? $this->borrar() : $this->crear();
    }

    private function crear(): int
    {
        $clave = (string) $this->option('clave');

        foreach (self::CUENTAS as $cuenta) {
            foreach ($cuenta['roles'] as $rol) {
                Role::findOrCreate($rol, 'web');
            }

            $usuario = User::updateOrCreate(
                ['email' => $cuenta['correo'] . self::DOMINIO],
                [
                    'name' => $cuenta['nombre'],
                    'password' => Hash::make($clave),
                    'id_gerencia' => $cuenta['gerencia'],
                ],
            );

            $usuario->syncRoles($cuenta['roles']);
        }

        $this->mostrar($clave);

        return self::SUCCESS;
    }

    private function borrar(): int
    {
        $cuantas = User::query()
            ->where('email', 'like', '%' . self::DOMINIO)
            ->delete();

        $this->components->info("Borradas {$cuantas} cuentas de prueba.");

        return self::SUCCESS;
    }

    private function mostrar(string $clave): void
    {
        $this->newLine();
        $this->components->info('Cuentas listas. La contraseña es la misma para todas: ' . $clave);
        $this->newLine();

        $filas = [];

        foreach (self::CUENTAS as $cuenta) {
            $gerencia = $cuenta['gerencia'] === null
                ? 'todas'
                : (\App\Models\Gerencia::find($cuenta['gerencia'])?->nombre ?? $cuenta['gerencia']);

            $filas[] = [
                $cuenta['correo'] . self::DOMINIO,
                implode(' + ', $cuenta['roles']),
                $gerencia,
            ];
        }

        $this->table(['Correo', 'Rol', 'Gerencia'], $filas);

        $this->line('  Entre por ' . config('app.url') . '/admin');
        $this->line('  Para quitarlas: php artisan cuentas:prueba --borrar');
    }
}
