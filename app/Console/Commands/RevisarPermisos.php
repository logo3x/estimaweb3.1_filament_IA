<?php

namespace App\Console\Commands;

use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Muestra quien puede hacer que.
 *
 * Sirve para revisar los permisos de un vistazo, sin tener que entrar al
 * panel con cada rol. Util tras un despliegue o cuando alguien dice que no
 * ve una seccion que deberia ver.
 */
class RevisarPermisos extends Command
{
    protected $signature = 'permisos:revisar {--usuario= : Correo de un usuario concreto}';

    protected $description = 'Muestra qué puede hacer cada rol en el panel';

    public function handle(): int
    {
        $correo = $this->option('usuario');

        return $correo
            ? $this->revisarUsuario((string) $correo)
            : $this->revisarRoles();
    }

    /** La matriz de roles por capacidad. */
    private function revisarRoles(): int
    {
        $roles = Role::query()->orderBy('name')->pluck('name')->all();
        $permisos = new Permisos();

        $filas = [];

        foreach (Capacidad::cases() as $capacidad) {
            $fila = [$capacidad->value];

            foreach ($roles as $rol) {
                /**
                 * Se pregunta con un usuario que tiene SOLO ese rol, creado
                 * al vuelo y sin guardar.
                 *
                 * Tomar a una persona real daria una matriz enganosa: seis
                 * usuarios del sistema acumulan varios roles -una llega a
                 * tener cuatro-, de modo que el primer "Creador" que
                 * aparece es tambien Administrador y saldria pudiendolo
                 * todo.
                 */
                $fila[] = $permisos->puede($this->usuarioSoloCon($rol), $capacidad)
                    ? '<fg=green>sí</>'
                    : '<fg=red>no</>';
            }

            $filas[] = $fila;
        }

        $this->table(array_merge(['Capacidad'], $roles), $filas);

        $this->line('Cada columna es ese rol POR SI SOLO.');
        $this->line('Los roles suman: quien tiene varios puede lo de todos ellos.');
        $this->line('Para ver a una persona concreta: php artisan permisos:revisar --usuario=correo@dominio.com');

        return self::SUCCESS;
    }

    /**
     * Un usuario con ese unico rol, sin tocar la base de datos.
     *
     * No se guarda: solo sirve para preguntarle a las reglas.
     */
    private function usuarioSoloCon(string $rol): User
    {
        $usuario = new User();
        $usuario->setRelation('roles', collect([Role::query()->where('name', $rol)->first()]));

        return $usuario;
    }

    private function revisarUsuario(string $correo): int
    {
        $usuario = User::query()->where('email', $correo)->first();

        if (! $usuario) {
            $this->error("No existe ningún usuario con el correo {$correo}.");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Usuario', $usuario->name);
        $this->components->twoColumnDetail('Roles', $usuario->getRoleNames()->implode(', ') ?: 'ninguno');
        $this->components->twoColumnDetail('Gerencia', (string) ($usuario->id_gerencia ?: '—'));
        $this->components->twoColumnDetail(
            'Accede al panel',
            $usuario->hasAnyRole(User::ROLES_CON_ACCESO_AL_PANEL) ? '<fg=green>sí</>' : '<fg=red>no</>'
        );

        $this->newLine();

        $permisos = new Permisos();

        foreach (Capacidad::cases() as $capacidad) {
            $this->components->twoColumnDetail(
                '  ' . $capacidad->value,
                $permisos->puede($usuario, $capacidad) ? '<fg=green>sí</>' : '<fg=red>no</>'
            );
        }

        return self::SUCCESS;
    }
}
