<?php

namespace Tests\Feature;

use App\Filament\Pages\FactoresLegales;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\OrigenFactor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las pantallas de administracion: usuarios y factores legales.
 */
class PanelAdministracionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(
            User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'Administrador'))
                ->firstOrFail()
        );
    }

    /** El listado de usuarios carga. */
    public function test_el_listado_de_usuarios_carga(): void
    {
        Livewire::test(ListUsers::class)
            ->assertOk()
            ->assertCanSeeTableRecords(User::query()->orderBy('name')->limit(10)->get());
    }

    /**
     * El listado no expone el hash de la contrasena.
     *
     * Un listado acaba en capturas de pantalla y exportaciones.
     */
    public function test_el_listado_no_expone_contrasenas(): void
    {
        /**
         * Se comprueba sobre las columnas declaradas y sobre el modelo
         * serializado, no sobre el HTML: Livewire incrusta su propio estado
         * en la pagina y eso ensuciaria la busqueda.
         */
        $columnas = array_keys(
            \App\Filament\Resources\Users\UserResource::table(
                app(\Filament\Tables\Table::class, ['livewire' => Livewire::test(ListUsers::class)->instance()])
            )->getColumns()
        );

        $this->assertNotContains('password', $columnas);
        $this->assertNotContains('remember_token', $columnas);

        $serializado = User::query()->firstOrFail()->toArray();

        $this->assertArrayNotHasKey('password', $serializado);
        $this->assertArrayNotHasKey('remember_token', $serializado);
    }

    /** Se crea un usuario y su contrasena queda cifrada. */
    public function test_al_crear_un_usuario_la_contrasena_se_cifra(): void
    {
        $correo = 'nuevo.usuario@prueba.test';

        User::query()->where('email', $correo)->delete();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Usuario Nuevo',
                'email' => $correo,
                'password' => 'contrasena-segura-1234',
                'roles' => [],
            ])
            ->call('create');

        $creado = User::query()->where('email', $correo)->first();

        $this->assertNotNull($creado, 'No se creo el usuario.');

        $this->assertNotSame('contrasena-segura-1234', $creado->password);
        $this->assertTrue(Hash::check('contrasena-segura-1234', $creado->password));

        $creado->delete();
    }

    /**
     * Al editar sin tocar la contrasena, esta se conserva.
     *
     * Si el formulario la guardara vacia, el usuario quedaria sin poder
     * entrar sin que nadie se diera cuenta.
     */
    public function test_editar_sin_contrasena_conserva_la_actual(): void
    {
        $usuario = User::query()->whereDoesntHave('cotizaciones')->firstOrFail();
        $hashOriginal = $usuario->password;

        Livewire::test(EditUser::class, ['record' => $usuario->getKey()])
            ->fillForm([
                'name' => $usuario->name . ' (editado)',
                'email' => $usuario->email,
                'password' => '',
            ])
            ->call('save');

        $this->assertSame($hashOriginal, $usuario->fresh()->password);

        $usuario->update(['name' => str_replace(' (editado)', '', $usuario->fresh()->name)]);
    }

    /** La pantalla de factores legales carga con los valores vigentes. */
    public function test_la_pantalla_de_factores_carga(): void
    {
        $factores = OrigenFactor::vigentes();

        Livewire::test(FactoresLegales::class)
            ->assertOk()
            ->assertFormSet([
                'Salario_min_vigente' => (string) $factores->Salario_min_vigente,
            ]);
    }

    /**
     * Las horas trabajadas al dia no pueden ser cero.
     *
     * Dividen el salario diario para obtener el valor de la hora, de la que
     * salen todos los recargos.
     */
    public function test_los_factores_rechazan_horas_en_cero(): void
    {
        Livewire::test(FactoresLegales::class)
            ->fillForm(['Pro_horas_trabajadasdia' => 0])
            ->call('guardar')
            ->assertHasFormErrors(['Pro_horas_trabajadasdia']);
    }

    /** El salario minimo tampoco puede ser cero. */
    public function test_los_factores_rechazan_un_salario_minimo_en_cero(): void
    {
        Livewire::test(FactoresLegales::class)
            ->fillForm(['Salario_min_vigente' => 0])
            ->call('guardar')
            ->assertHasFormErrors(['Salario_min_vigente']);
    }

    /** Un cambio valido se guarda. */
    public function test_los_factores_se_pueden_actualizar(): void
    {
        $factores = OrigenFactor::vigentes();
        $original = $factores->aux_edu;

        Livewire::test(FactoresLegales::class)
            ->fillForm(['aux_edu' => $original + 1000])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $this->assertSame($original + 1000, (int) OrigenFactor::vigentes()->aux_edu);

        $factores->update(['aux_edu' => $original]);
    }

    /**
     * La columna de acceso refleja la regla real.
     *
     * Tener un rol no basta: debe ser uno de los previstos.
     */
    public function test_la_columna_de_acceso_usa_la_regla_real(): void
    {
        foreach (User::query()->with('roles')->limit(10)->get() as $usuario) {
            $this->assertSame(
                $usuario->hasAnyRole(User::ROLES_CON_ACCESO_AL_PANEL),
                $usuario->canAccessPanel(filament()->getPanel('admin')),
                "La regla de acceso no coincide para {$usuario->email}."
            );
        }
    }

    /** El listado de usuarios no dispara una consulta de roles por fila. */
    public function test_el_listado_de_usuarios_no_dispara_una_consulta_por_fila(): void
    {
        $consultas = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$consultas): void {
            $consultas++;
        });

        Livewire::test(ListUsers::class)->assertOk();

        $this->assertLessThan(
            40,
            $consultas,
            "El listado de usuarios disparo {$consultas} consultas."
        );
    }
}
