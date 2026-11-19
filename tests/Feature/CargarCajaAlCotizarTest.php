<?php

namespace Tests\Feature;

use App\Filament\Resources\Cotizacions\RelationManagers\HerramientasRelationManager;
use App\Models\Cotizacion;
use App\Models\PrecargadoHerramienta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cargar una caja al cotizar, sin crear antes un grupo a mano.
 *
 * La accion existia solo como accion de FILA, de modo que no aparecia hasta
 * que hubiera al menos un grupo. Justo al empezar una cotizacion -que es
 * cuando una caja ahorra mas trabajo- la tabla esta vacia y no habia donde
 * pulsar: las cajas quedaban sin usar.
 */
class CargarCajaAlCotizarTest extends TestCase
{
    use DatabaseTransactions;

    private function usuarioConCaja(): array
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('Administrador');

        $caja = PrecargadoHerramienta::query()->create([
            'nombre' => 'Caja de prueba',
            'id_user' => $usuario->id,
        ]);

        /** Dos articulos reales del catalogo. */
        $ids = \App\Models\DatosHerramienta::query()->limit(2)->pluck('id');

        /**
         * OJO: la caja usa `id_datos_herramienta` (singular) y el grupo de la
         * cotizacion `id_datos_herramientas` (plural). Son tablas distintas y
         * la diferencia es solo esa letra.
         */
        foreach ($ids as $id) {
            $caja->elementos()->create([
                PrecargadoHerramienta::columnaDelCatalogo() => $id,
            ]);
        }

        return [$usuario, $caja];
    }

    /**
     * Con la tabla vacia, la caja crea el grupo y mete sus articulos.
     *
     * Es el caso que estaba roto.
     */
    public function test_cargar_una_caja_crea_el_grupo_con_sus_articulos(): void
    {
        [$usuario, $caja] = $this->usuarioConCaja();
        $this->actingAs($usuario);

        $cotizacion = Cotizacion::findOrFail(55);
        $antes = $cotizacion->herramientas()->count();

        Livewire::test(HerramientasRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => \App\Filament\Resources\Cotizacions\Pages\EditCotizacion::class,
        ])
            ->callTableAction('cargarCajaNueva', data: [
                'caja' => $caja->id,
                'nombre' => 'Grupo desde caja',
                'periodo_evaluacion' => 1,
                'vrepo_anual' => 0,
                'vcalibracion_anual' => 0,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($antes + 1, $cotizacion->herramientas()->count());

        $grupo = $cotizacion->herramientas()->where('nombre', 'Grupo desde caja')->first();

        $this->assertNotNull($grupo, 'No se creó el grupo.');
        $this->assertSame(2, $grupo->items()->count(), 'El grupo se creó vacío.');
    }

    /** Sin cajas propias, el boton no se ofrece. */
    public function test_sin_cajas_propias_no_se_ofrece_el_boton(): void
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('Administrador');
        $this->actingAs($usuario);

        $html = Livewire::test(HerramientasRelationManager::class, [
            'ownerRecord' => Cotizacion::findOrFail(55),
            'pageClass' => \App\Filament\Resources\Cotizacions\Pages\EditCotizacion::class,
        ])->html();

        $this->assertStringNotContainsString('Cargar caja', $html);
    }

    /**
     * Las cajas de otro no aparecen.
     *
     * Son listas de trabajo personales, y ademas llevan precios.
     */
    public function test_no_se_ven_las_cajas_de_otros(): void
    {
        [, $cajaAjena] = $this->usuarioConCaja();

        $otro = User::factory()->create(['id_gerencia' => '4']);
        $otro->assignRole('Administrador');
        $this->actingAs($otro);

        $suyas = PrecargadoHerramienta::query()->de($otro)->pluck('id');

        $this->assertNotContains($cajaAjena->id, $suyas->all());
    }
}
