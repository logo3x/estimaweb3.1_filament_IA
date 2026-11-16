<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nadie se asciende a si mismo.
 *
 * El fallo que vigila esta clase: la policy dejaba a cualquiera editar su
 * propia ficha -para cambiar su nombre o su clave- pero el formulario que
 * se abria era el de administracion, con el selector de roles dentro. Un
 * Creador podia entrar a `/admin/usuarios/{su_id}/edit`, marcarse
 * "Administrador" y quedarse con todo: ver las cotizaciones de todas las
 * gerencias, aprobar el nivel financiero y tocar los factores legales.
 *
 * Anulaba de un paso los tres controles del sistema.
 */
class EscaladaDePrivilegiosTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** Un creador no abre la ficha de nadie, ni la suya. */
    public function test_un_creador_no_edita_su_propia_ficha(): void
    {
        $creador = $this->usuario('Creador');

        $this->assertFalse(
            Gate::forUser($creador)->allows('update', $creador),
            'Un creador puede editar su propia ficha: desde ahí se asigna roles.'
        );
    }

    /** Ni entrando por la dirección. */
    public function test_la_pantalla_de_edicion_se_cierra_al_creador(): void
    {
        $creador = $this->usuario('Creador');
        $this->actingAs($creador);

        Livewire::test(EditUser::class, ['record' => $creador->id])
            ->assertForbidden();
    }

    /**
     * Un gerente tampoco: gestionar usuarios es de administracion.
     *
     * Es el rol mas numeroso del sistema (51 cuentas).
     */
    public function test_un_gerente_no_edita_su_propia_ficha(): void
    {
        $gerente = $this->usuario('Gerente');

        $this->assertFalse(Gate::forUser($gerente)->allows('update', $gerente));
    }

    /** La administracion sí gestiona usuarios, que es su trabajo. */
    public function test_la_administracion_edita_fichas(): void
    {
        $admin = $this->usuario('Administrador');

        $this->assertTrue(Gate::forUser($admin)->allows('update', $admin));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $this->usuario('Creador')));
    }

    /**
     * Un administrador no se concede super_admin.
     *
     * Ese rol salta todas las comprobaciones del sistema, incluido el
     * bloqueo de lo ya aprobado. Solo lo reparte quien ya lo tiene.
     */
    public function test_un_administrador_no_se_asciende_a_super_admin(): void
    {
        $admin = $this->usuario('Administrador');
        $this->actingAs($admin);

        $rolTotal = \Spatie\Permission\Models\Role::query()
            ->where('name', \App\Dominio\Permisos\Permisos::ROL_TOTAL)
            ->firstOrFail();

        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->fillForm(['roles' => [$rolTotal->id]])
            ->call('save');

        $this->assertFalse(
            $admin->fresh()->hasRole(\App\Dominio\Permisos\Permisos::ROL_TOTAL),
            'Un administrador se concedió super_admin.'
        );
    }

    /**
     * Y no se cambia de gerencia para ver las cotizaciones de otra.
     *
     * Seria la otra via para el mismo fin: sin tocar roles, mudarse a la
     * gerencia cuyos precios se quieren mirar.
     */
    public function test_nadie_se_cambia_de_gerencia_para_ver_otras_cotizaciones(): void
    {
        $creador = $this->usuario('Creador');

        /**
         * Mudarse de gerencia es la otra via para el mismo fin: sin tocar
         * roles, pasarse a la zona cuyos precios se quieren mirar. Se
         * cierra por el mismo sitio, al no poder abrir la ficha.
         */
        $this->assertFalse(Gate::forUser($creador)->allows('update', $creador));
    }

    /**
     * Cada quien sigue pudiendo cambiar su clave.
     *
     * Quitar la autoedicion no puede dejar a nadie sin poder cambiar su
     * propia contraseña: para eso esta la pagina de perfil, aparte del
     * recurso de administracion.
     */
    public function test_cualquiera_puede_abrir_su_perfil(): void
    {
        $creador = $this->usuario('Creador');
        $this->actingAs($creador);

        $this->get('/admin/profile')->assertSuccessful();
    }
}
