<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los roles sin los que nadie puede entrar al panel.
 *
 * Si la base llegara al servidor sin la tabla de roles, no existiria el
 * `super_admin` que asignar y el panel quedaria cerrado para todos. Y en
 * cPanel no hay consola para arreglarlo a mano.
 */
class RolesDeArranqueTest extends TestCase
{
    use DatabaseTransactions;

    /** Los cinco roles del sistema quedan creados. */
    public function test_crea_los_cinco_roles(): void
    {
        Role::query()->delete();

        (new DatabaseSeeder())->run();

        foreach (['super_admin', 'Administrador', 'Gerente', 'Director', 'Creador'] as $rol) {
            $this->assertDatabaseHas('roles', ['name' => $rol, 'guard_name' => 'web']);
        }
    }

    /**
     * Ejecutarlo dos veces no duplica nada.
     *
     * Se corre tras cada despliegue; si duplicara roles, las personas
     * perderian sus permisos al quedar ligadas al rol viejo.
     */
    public function test_ejecutarlo_dos_veces_no_duplica(): void
    {
        (new DatabaseSeeder())->run();
        $despuesDeLaPrimera = Role::count();

        (new DatabaseSeeder())->run();

        $this->assertSame($despuesDeLaPrimera, Role::count());
    }

    /**
     * No inventa usuarios.
     *
     * El seeder de Laravel traia un `test@example.com`. En un sistema con
     * aprobaciones, un usuario falso es una persona que puede aprobar.
     */
    public function test_no_crea_usuarios_de_ejemplo(): void
    {
        $antes = \App\Models\User::count();

        (new DatabaseSeeder())->run();

        $this->assertSame($antes, \App\Models\User::count());
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
