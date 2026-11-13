<?php

namespace Tests\Feature;

use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Filament\Pages\FactoresLegales;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Quien puede hacer que en el panel.
 *
 * Hasta aqui el sistema tenia una sola puerta: quien entraba al panel
 * entraba a todo. Los diecisiete usuarios con rol Creador podian borrar
 * usuarios y cambiar los factores legales, que mueven el precio de todas
 * las cotizaciones.
 */
class PermisosPorRolTest extends TestCase
{
    use DatabaseTransactions;

    private Permisos $permisos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permisos = new Permisos();
    }

    private function con(string ...$roles): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);

        foreach ($roles as $rol) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    }

    /** Un Creador no gestiona usuarios. */
    public function test_un_creador_no_gestiona_usuarios(): void
    {
        $this->assertFalse($this->permisos->puede($this->con('Creador'), Capacidad::AdministrarUsuarios));
    }

    /** Un Creador no toca los factores legales. */
    public function test_un_creador_no_toca_los_factores_legales(): void
    {
        $this->assertFalse($this->permisos->puede($this->con('Creador'), Capacidad::AdministrarFactoresLegales));
    }

    /** Un Gerente tampoco: los factores son de administracion. */
    public function test_un_gerente_no_toca_los_factores_legales(): void
    {
        $this->assertFalse($this->permisos->puede($this->con('Gerente'), Capacidad::AdministrarFactoresLegales));
    }

    /** Un Creador consulta los catalogos pero no los modifica. */
    public function test_un_creador_consulta_catalogos_pero_no_los_cambia(): void
    {
        $creador = $this->con('Creador');

        $this->assertTrue($this->permisos->puede($creador, Capacidad::VerCatalogos));
        $this->assertFalse($this->permisos->puede($creador, Capacidad::EditarCatalogos));
    }

    /** Un Gerente si corrige un precio base. */
    public function test_un_gerente_corrige_los_precios_base(): void
    {
        $this->assertTrue($this->permisos->puede($this->con('Gerente'), Capacidad::EditarCatalogos));
    }

    /**
     * Los roles SUMAN.
     *
     * Seis personas del sistema tienen varios a la vez; preguntar solo por
     * el primero dejaria fuera a quien si deberia poder.
     */
    public function test_los_roles_se_suman(): void
    {
        $ambos = $this->con('Creador', 'Administrador');

        $this->assertTrue($this->permisos->puede($ambos, Capacidad::AdministrarUsuarios));
        $this->assertTrue($this->permisos->puede($ambos, Capacidad::EditarCatalogos));
    }

    /** super_admin puede todo, sin enumerarlo en cada regla. */
    public function test_super_admin_puede_todo(): void
    {
        $super = $this->con(Permisos::ROL_TOTAL);

        foreach (Capacidad::cases() as $capacidad) {
            $this->assertTrue(
                $this->permisos->puede($super, $capacidad),
                "super_admin no puede '{$capacidad->value}'."
            );
        }
    }

    /** Sin sesion no se puede nada. */
    public function test_sin_usuario_no_se_puede_nada(): void
    {
        foreach (Capacidad::cases() as $capacidad) {
            $this->assertFalse($this->permisos->puede(null, $capacidad));
        }
    }

    /** Cada capacidad explica por que bloquea. */
    #[DataProvider('todasLasCapacidades')]
    public function test_cada_capacidad_explica_el_bloqueo(string $capacidad): void
    {
        $motivo = Capacidad::from($capacidad)->motivoDelBloqueo();

        $this->assertNotSame('', $motivo);
        $this->assertStringEndsWith('.', $motivo);
    }

    /** @return array<string, array{string}> */
    public static function todasLasCapacidades(): array
    {
        $casos = [];

        foreach (Capacidad::cases() as $capacidad) {
            $casos[$capacidad->value] = [$capacidad->value];
        }

        return $casos;
    }

    /** La policy del catalogo refleja las capacidades. */
    public function test_la_policy_del_catalogo_aplica_las_reglas(): void
    {
        $creador = $this->con('Creador');
        $gerente = $this->con('Gerente');

        $this->assertTrue(Gate::forUser($creador)->allows('viewAny', \App\Models\DatosHerramienta::class));
        $this->assertFalse(Gate::forUser($creador)->allows('create', \App\Models\DatosHerramienta::class));
        $this->assertTrue(Gate::forUser($gerente)->allows('create', \App\Models\DatosHerramienta::class));
    }

    /** Los ocho catalogos comparten la misma regla. */
    public function test_los_ocho_catalogos_se_comportan_igual(): void
    {
        $creador = $this->con('Creador');

        foreach ([
            \App\Models\DatosHerramienta::class,
            \App\Models\DatosEquipo::class,
            \App\Models\DatosConsumible::class,
            \App\Models\DatosComunicacion::class,
            \App\Models\Epp::class,
            \App\Models\ExamenVacuna::class,
            \App\Models\Turno::class,
            \App\Models\Gerencia::class,
        ] as $modelo) {
            $this->assertTrue(
                Gate::forUser($creador)->allows('viewAny', $modelo),
                "Un creador no puede consultar {$modelo}."
            );

            $this->assertFalse(
                Gate::forUser($creador)->allows('create', $modelo),
                "Un creador puede modificar {$modelo}."
            );
        }
    }

    /**
     * Nadie se borra a si mismo.
     *
     * Un administrador que se borra pierde el acceso y deja el sistema sin
     * quien lo recupere, porque solo la administracion gestiona usuarios.
     */
    public function test_nadie_se_borra_a_si_mismo(): void
    {
        $admin = $this->con('Administrador');

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $this->con('Creador')));
    }

    /** A super_admin solo lo borra otro super_admin. */
    public function test_un_administrador_no_borra_a_super_admin(): void
    {
        $this->assertFalse(
            Gate::forUser($this->con('Administrador'))->allows('delete', $this->con(Permisos::ROL_TOTAL))
        );
    }

    /** Cualquiera puede ver y editar su propia ficha. */
    public function test_cada_uno_edita_su_propia_ficha(): void
    {
        $creador = $this->con('Creador');

        $this->assertTrue(Gate::forUser($creador)->allows('view', $creador));
        $this->assertTrue(Gate::forUser($creador)->allows('update', $creador));
        $this->assertFalse(Gate::forUser($creador)->allows('update', $this->con('Gerente')));
    }

    /**
     * Una cotizacion aprobada no se borra, ni siendo administrador.
     *
     * Borrar arrastra items, recursos y asignaciones de algo que ya se
     * envio al cliente.
     */
    public function test_una_cotizacion_aprobada_no_se_borra(): void
    {
        $admin = $this->con('Administrador');
        $cotizacion = Cotizacion::findOrFail(55);
        $original = $cotizacion->only(['aprobacion1', 'aprobacion2']);

        try {
            $cotizacion->forceFill(['aprobacion1' => 'SI', 'aprobacion2' => 'SI'])->save();

            $this->assertFalse(Gate::forUser($admin)->allows('delete', $cotizacion->refresh()));
        } finally {
            $cotizacion->forceFill($original)->save();
        }
    }

    /** Un Creador no borra cotizaciones aunque esten en borrador. */
    public function test_un_creador_no_borra_cotizaciones(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);
        $original = $cotizacion->only(['aprobacion1', 'aprobacion2']);

        try {
            $cotizacion->forceFill(['aprobacion1' => null, 'aprobacion2' => null])->save();

            $this->assertFalse(
                Gate::forUser($this->con('Creador'))->allows('delete', $cotizacion->refresh())
            );
        } finally {
            $cotizacion->forceFill($original)->save();
        }
    }

    /** La pantalla de usuarios se cierra a quien no administra. */
    public function test_la_pantalla_de_usuarios_se_cierra_a_un_creador(): void
    {
        $this->actingAs($this->con('Creador'));

        Livewire::test(ListUsers::class)->assertForbidden();
    }

    /** Y se abre a la administracion. */
    public function test_la_pantalla_de_usuarios_se_abre_a_la_administracion(): void
    {
        $this->actingAs($this->con('Administrador'));

        Livewire::test(ListUsers::class)->assertOk();
    }

    /** La pagina de factores no se abre escribiendo la URL. */
    public function test_los_factores_legales_no_se_abren_por_url(): void
    {
        $this->actingAs($this->con('Gerente'));

        $this->get('/admin/factores-legales')->assertForbidden();
    }

    /** La administracion si entra a los factores. */
    public function test_la_administracion_entra_a_los_factores(): void
    {
        $this->actingAs($this->con('Administrador'));

        $this->get('/admin/factores-legales')->assertOk();
    }

    /**
     * Guardar los factores se comprueba aparte de entrar.
     *
     * Una peticion de Livewire puede llegar sin pasar por la pantalla: si
     * el permiso solo estuviera en el menu, bastaria con saltarselo.
     */
    public function test_guardar_los_factores_exige_permiso(): void
    {
        $this->actingAs($this->con('Gerente'));

        Livewire::test(FactoresLegales::class)->assertForbidden();
    }

    /** El menu no muestra lo que el usuario no puede abrir. */
    public function test_el_menu_oculta_lo_que_no_se_puede_abrir(): void
    {
        $this->actingAs($this->con('Creador'));

        $this->assertFalse(FactoresLegales::canAccess());
    }
}
