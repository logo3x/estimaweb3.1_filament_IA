<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Los roles sin los que nadie puede entrar.
 *
 * El sistema no se instala vacio: se despliega sobre la base que ya existe,
 * con sus 52 personas y sus cotizaciones. Este seeder **no** crea usuarios ni
 * datos de ejemplo -haria falso el historial-.
 *
 * Esta aqui por un caso concreto: si la base llegara sin la tabla de roles
 * -una base nueva, una restauracion a medias-, nadie podria entrar al panel.
 * No existiria el rol `super_admin` que asignar, y no hay consola en el
 * servidor para crearlo a mano.
 *
 * Es seguro ejecutarlo mas de una vez: no duplica ni pisa lo que ya hay.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Los cinco roles del sistema.
     *
     * Se corresponden con los de la base real. Los permisos no se declaran
     * aqui: viven en `App\Dominio\Permisos`, que decide por rol.
     *
     * @var list<string>
     */
    private const ROLES = [
        'super_admin',
        'Administrador',
        'Gerente',
        'Director',
        'Creador',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $rol) {
            Role::findOrCreate($rol, 'web');
        }

        $this->command?->info('Roles asegurados: ' . implode(', ', self::ROLES) . '.');
    }
}
