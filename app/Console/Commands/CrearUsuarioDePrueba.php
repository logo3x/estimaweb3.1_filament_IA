<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crea o restaura el usuario con el que se prueba el panel en local.
 *
 * Hace falta porque las contrasenas de los usuarios reales vienen cifradas
 * del respaldo de produccion y no se conocen. Y porque `datos:cargar` recrea
 * la base entera, de modo que un usuario creado a mano desaparece en cuanto
 * se refrescan los datos.
 *
 * Se niega a ejecutarse fuera de local: este usuario tiene rol de
 * administrador y una contrasena conocida.
 */
class CrearUsuarioDePrueba extends Command
{
    protected $signature = 'usuario:prueba
        {--correo=prueba.local@estimaweb.test}
        {--clave=prueba1234}
        {--rol=Administrador}';

    protected $description = 'Crea el usuario local con el que se prueba el panel';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Este comando solo corre en local: crea una cuenta con contrasena conocida.');

            return self::FAILURE;
        }

        $correo = (string) $this->option('correo');
        $rol = (string) $this->option('rol');

        if (! in_array($rol, User::ROLES_CON_ACCESO_AL_PANEL, true)) {
            $this->error("El rol '{$rol}' no da acceso al panel.");
            $this->line('Roles validos: ' . implode(', ', User::ROLES_CON_ACCESO_AL_PANEL));

            return self::FAILURE;
        }

        $usuario = User::query()->firstOrNew(['email' => $correo]);

        $usuario->name = 'Prueba Local';
        $usuario->password = (string) $this->option('clave');
        /** La columna es NOT NULL sin valor por defecto. */
        $usuario->gerencia = $usuario->gerencia ?: '';
        $usuario->save();

        $usuario->syncRoles([$rol]);

        $this->info("Usuario listo: {$correo}");
        $this->line('  contrasena: ' . $this->option('clave'));
        $this->line('  rol: ' . $rol);
        $this->line('  accede al panel: ' . ($usuario->fresh()->canAccessPanel(filament()->getPanel('admin')) ? 'si' : 'NO'));

        return self::SUCCESS;
    }
}
