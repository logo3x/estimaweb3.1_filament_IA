<?php

namespace Tests\Feature;

use App\Filament\Resources\CajasConsumibles\Pages\GestionarCajaConsumible;
use App\Filament\Resources\CajasEpps\Pages\GestionarCajaEpp;
use App\Filament\Resources\CajasEquipos\Pages\GestionarCajaEquipo;
use App\Filament\Resources\CajasHerramientas\Pages\GestionarCajaHerramienta;
use App\Models\DatosHerramienta;
use App\Models\PrecargadoHerramienta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Crear y mantener las cajas propias.
 *
 * Hasta aqui se podian USAR al cotizar pero no crear: habia que escribirlas
 * directamente en la base. Son listas personales, asi que cada quien
 * gestiona las suyas.
 */
class GestionDeCajasTest extends TestCase
{
    use DatabaseTransactions;

    private function comoUsuario(string $rol = 'Creador'): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        $this->actingAs($usuario);

        return $usuario;
    }

    /** @return array<string, array{class-string}> */
    public static function pantallas(): array
    {
        return [
            'herramientas' => [GestionarCajaHerramienta::class],
            'equipos' => [GestionarCajaEquipo::class],
            'consumibles' => [GestionarCajaConsumible::class],
            'EPP' => [GestionarCajaEpp::class],
        ];
    }

    /** Las cuatro pantallas cargan. */
    #[DataProvider('pantallas')]
    public function test_la_pantalla_carga(string $pantalla): void
    {
        $this->comoUsuario();

        Livewire::test($pantalla)->assertOk();
    }

    /**
     * Cualquiera que entre al panel puede tener sus cajas.
     *
     * No es un permiso de administracion: un creador las necesita tanto
     * como un gerente.
     */
    #[DataProvider('pantallas')]
    public function test_un_creador_puede_entrar(string $pantalla): void
    {
        $this->comoUsuario('Creador');

        Livewire::test($pantalla)->assertOk();
    }

    /** Se crea una caja con sus articulos. */
    public function test_se_crea_una_caja_con_articulos(): void
    {
        $usuario = $this->comoUsuario();

        $articulos = DatosHerramienta::query()->limit(3)->pluck('id')->all();

        Livewire::test(GestionarCajaHerramienta::class)
            ->callAction('create', data: [
                'nombre' => 'Caja de prueba',
                'articulos' => $articulos,
            ])
            ->assertHasNoActionErrors();

        $caja = PrecargadoHerramienta::query()
            ->where('nombre', 'Caja de prueba')
            ->first();

        $this->assertNotNull($caja, 'No se creó la caja.');
        $this->assertSame($usuario->id, (int) $caja->id_user, 'La caja no quedó a nombre de quien la creó.');
        $this->assertEqualsCanonicalizing($articulos, $caja->idsDelCatalogo());
    }

    /** Una caja sin articulos se puede crear, pero avisa en el listado. */
    public function test_se_crea_una_caja_vacia(): void
    {
        $this->comoUsuario();

        Livewire::test(GestionarCajaHerramienta::class)
            ->callAction('create', data: ['nombre' => 'Caja vacía'])
            ->assertHasNoActionErrors();

        $caja = PrecargadoHerramienta::query()->where('nombre', 'Caja vacía')->first();

        $this->assertNotNull($caja);
        $this->assertSame(0, $caja->cuantosElementos());
    }

    /** Se cambian los articulos de una caja existente. */
    public function test_se_edita_una_caja(): void
    {
        $usuario = $this->comoUsuario();

        $caja = PrecargadoHerramienta::query()->create([
            'nombre' => 'Caja por corregir',
            'id_user' => $usuario->id,
        ]);
        $caja->elementos()->create([
            'id_datos_herramienta' => DatosHerramienta::query()->value('id'),
        ]);

        $nuevos = DatosHerramienta::query()->orderByDesc('id')->limit(2)->pluck('id')->all();

        Livewire::test(GestionarCajaHerramienta::class)
            ->callTableAction('edit', $caja, data: [
                'nombre' => 'Caja corregida',
                'articulos' => $nuevos,
            ])
            ->assertHasNoTableActionErrors();

        $caja->refresh();

        $this->assertSame('Caja corregida', $caja->nombre);
        $this->assertEqualsCanonicalizing($nuevos, $caja->idsDelCatalogo());
    }

    /** Se borra una caja propia. */
    public function test_se_borra_una_caja(): void
    {
        $usuario = $this->comoUsuario();

        $caja = PrecargadoHerramienta::query()->create([
            'nombre' => 'Caja por borrar',
            'id_user' => $usuario->id,
        ]);

        Livewire::test(GestionarCajaHerramienta::class)
            ->callTableAction('delete', $caja);

        $this->assertNull(PrecargadoHerramienta::query()->find($caja->id));
    }

    /**
     * Duplicar una caja se lleva sus articulos.
     *
     * Duplicar solo el nombre no sirve de nada: lo que cuesta armar es la
     * lista.
     */
    public function test_duplicar_se_lleva_los_articulos(): void
    {
        $usuario = $this->comoUsuario();

        $caja = PrecargadoHerramienta::query()->create([
            'nombre' => 'Caja original',
            'id_user' => $usuario->id,
        ]);

        $articulos = DatosHerramienta::query()->limit(2)->pluck('id')->all();

        foreach ($articulos as $id) {
            $caja->elementos()->create(['id_datos_herramienta' => $id]);
        }

        Livewire::test(GestionarCajaHerramienta::class)
            ->callTableAction('replicate', $caja);

        $copia = PrecargadoHerramienta::query()
            ->where('nombre', 'Caja original (copia)')
            ->first();

        $this->assertNotNull($copia, 'No se duplicó la caja.');
        $this->assertSame($usuario->id, (int) $copia->id_user, 'La copia no quedó a nombre de quien la duplicó.');
        $this->assertEqualsCanonicalizing($articulos, $copia->idsDelCatalogo());
    }

    /** En el listado solo salen las cajas propias. */
    public function test_el_listado_solo_muestra_las_propias(): void
    {
        $usuario = $this->comoUsuario();
        $ajeno = User::factory()->create(['id_gerencia' => '4']);

        $mia = PrecargadoHerramienta::query()->create([
            'nombre' => 'Mía',
            'id_user' => $usuario->id,
        ]);
        $suya = PrecargadoHerramienta::query()->create([
            'nombre' => 'Suya',
            'id_user' => $ajeno->id,
        ]);

        Livewire::test(GestionarCajaHerramienta::class)
            ->assertCanSeeTableRecords([$mia])
            ->assertCanNotSeeTableRecords([$suya]);
    }

    /** Las cajas sin dueño no son de nadie. */
    public function test_las_cajas_sin_duenio_no_se_listan(): void
    {
        $this->comoUsuario();

        $huerfana = PrecargadoHerramienta::query()->whereNull('id_user')->first();

        if (! $huerfana) {
            $this->markTestSkipped('Ya no hay cajas sin dueño en los datos.');
        }

        Livewire::test(GestionarCajaHerramienta::class)
            ->assertCanNotSeeTableRecords([$huerfana]);
    }

    /** El contador del menu cuenta solo las propias. */
    public function test_el_contador_cuenta_solo_las_propias(): void
    {
        $usuario = $this->comoUsuario();

        PrecargadoHerramienta::query()->create([
            'nombre' => 'Única mía',
            'id_user' => $usuario->id,
        ]);

        $this->assertSame(
            '1',
            \App\Filament\Resources\CajasHerramientas\CajaHerramientaResource::getNavigationBadge()
        );
    }
}
