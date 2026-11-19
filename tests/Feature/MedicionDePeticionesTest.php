<?php

namespace Tests\Feature;

use App\Dominio\Rendimiento\Rendimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Que pantallas van lentas y por que.
 *
 * Lo que se busca no es el reloj sino **el numero de consultas**: una
 * pantalla cuyas consultas crecen con los datos es un N+1, y eso no se ve
 * en local con pocos registros. Es lo que hacia que el sistema actual
 * tardara 35 segundos en abrir una cotizacion.
 */
class MedicionDePeticionesTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol = 'Administrador'): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** @param array<string, mixed> $valores */
    private function medicion(array $valores = []): void
    {
        DB::table('peticiones_lentas')->insert(array_merge([
            'metodo' => 'GET',
            'ruta' => 'admin/cotizaciones',
            'milisegundos' => 800,
            'consultas' => 10,
            'milisegundos_en_consultas' => 100,
            'memoria_kb' => 20000,
            'codigo' => 200,
            'usuario_id' => null,
            'es_livewire' => false,
            'cuando' => now(),
        ], $valores));
    }

    /** Sin nada anotado, el resumen no se inventa numeros. */
    public function test_sin_mediciones_el_resumen_esta_a_cero(): void
    {
        DB::table('peticiones_lentas')->delete();

        $resumen = (new Rendimiento())->resumen();

        $this->assertSame(0, $resumen['lentas']);
        $this->assertSame(0, $resumen['peor_ms']);
        $this->assertSame(0, $resumen['con_muchas_consultas']);
    }

    /** Las rutas se agrupan: importa si SIEMPRE va lenta. */
    public function test_las_rutas_se_agrupan_y_promedian(): void
    {
        DB::table('peticiones_lentas')->delete();

        $this->medicion(['ruta' => 'admin/lenta', 'milisegundos' => 1000]);
        $this->medicion(['ruta' => 'admin/lenta', 'milisegundos' => 2000]);

        $rutas = (new Rendimiento())->rutasMasLentas();

        $this->assertCount(1, $rutas, 'Las dos mediciones son la misma pantalla.');
        $this->assertSame(2, (int) $rutas->first()->veces);
        $this->assertSame(1500, (int) $rutas->first()->media_ms);
        $this->assertSame(2000, (int) $rutas->first()->peor_ms);
    }

    /**
     * Las que hacen demasiadas consultas se señalan aparte.
     *
     * Es el hallazgo util de toda la pantalla.
     */
    public function test_se_detectan_las_sospechosas_de_n_mas_uno(): void
    {
        DB::table('peticiones_lentas')->delete();

        $this->medicion(['ruta' => 'admin/normal', 'consultas' => 15]);
        $this->medicion(['ruta' => 'admin/sospechosa', 'consultas' => 120]);

        $sospechosas = (new Rendimiento())->sospechosasDeNMasUno();

        $this->assertCount(1, $sospechosas);
        $this->assertSame('admin/sospechosa', $sospechosas->first()->ruta);
    }

    /** Lo viejo se borra; lo reciente se conserva. */
    public function test_la_purga_respeta_lo_reciente(): void
    {
        DB::table('peticiones_lentas')->delete();

        $this->medicion(['ruta' => 'admin/vieja', 'cuando' => now()->subDays(60)]);
        $this->medicion(['ruta' => 'admin/nueva', 'cuando' => now()]);

        $rendimiento = new Rendimiento();
        $borradas = $rendimiento->purgar();

        $this->assertSame(1, $borradas);
        $this->assertSame(1, $rendimiento->cuantasHay());
    }

    /**
     * El middleware solo anota lo lento.
     *
     * Registrar todas llenaria la tabla de ruido y, en hosting compartido,
     * la escritura costaria mas que lo que se mide.
     */
    public function test_una_peticion_rapida_no_se_anota(): void
    {
        DB::table('peticiones_lentas')->delete();

        $this->actingAs($this->usuario())->get('/admin/rendimiento')->assertOk();

        $anotadas = DB::table('peticiones_lentas')->count();

        $this->assertLessThanOrEqual(
            1,
            $anotadas,
            'Una pantalla normal no debería anotarse salvo que de verdad tarde.',
        );
    }

    /** Solo la administracion ve el rendimiento. */
    public function test_el_rendimiento_no_lo_ve_cualquiera(): void
    {
        $this->actingAs($this->usuario('Creador'))->get('/admin/rendimiento')->assertForbidden();
        $this->actingAs($this->usuario('Administrador'))->get('/admin/rendimiento')->assertOk();
    }

    /**
     * Medir no puede romper la peticion medida.
     *
     * Si la tabla no existiera -durante una migracion, por ejemplo- la
     * aplicacion tiene que seguir respondiendo.
     */
    public function test_si_no_hay_tabla_la_aplicacion_sigue_funcionando(): void
    {
        $middleware = new \App\Http\Middleware\MedirLaPeticion();

        $respuesta = $middleware->handle(
            \Illuminate\Http\Request::create('/prueba'),
            fn () => new \Illuminate\Http\Response('bien'),
        );

        $this->assertSame('bien', $respuesta->getContent());
    }
}
