<?php

namespace Tests\Feature;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\CalcularCotizacion;
use App\Dominio\Estadisticas\Estadisticas;
use App\Dominio\Permisos\AlcanceDeCotizaciones;
use App\Models\Cotizacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Las estadisticas suman lo mismo que las ofertas.
 *
 * La pagina del sistema actual sumaba una columna guardada que, segun la
 * cotizacion, traia el total de una version vieja del codigo, el de datos
 * que ya no existen o cero. Estas pruebas fijan que aqui sale del calculo.
 */
class EstadisticasTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol = 'Administrador'): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** @return array<string, mixed> */
    private function cifras(?User $usuario = null, string $hoy = '2026-11-20 10:00'): array
    {
        return Estadisticas::crear()->calcular($usuario ?? $this->usuario(), CarbonImmutable::parse($hoy));
    }

    public function test_la_administracion_entra_y_un_creador_no(): void
    {
        Cache::flush();

        $this->actingAs($this->usuario('Creador'))->get('/admin/estadisticas')->assertForbidden();

        $this->actingAs($this->usuario())
            ->get('/admin/estadisticas')
            ->assertOk()
            ->assertSee('Estadísticas de cotizaciones')
            ->assertSee('Valor cotizado');
    }

    /** El valor total es la suma de los totales de las ofertas, uno por uno. */
    public function test_el_valor_total_es_la_suma_de_las_ofertas(): void
    {
        $calculo = CalcularCotizacion::crear();
        $esperado = Cotizacion::query()->get()->sum(fn (Cotizacion $c): float => $calculo->para($c)->totales->total);

        $cifras = $this->cifras();

        $this->assertSame(Cotizacion::query()->count(), $cifras['conteos']['total']);
        $this->assertEqualsWithDelta($esperado, $cifras['valores']['total'], 0.01);
    }

    /**
     * No se suma la columna guardada.
     *
     * La cotizacion 10 guarda $579.641,85 de unos recursos que ya no tiene:
     * su oferta vale cero. Si las estadisticas leyeran la columna, ese
     * dinero apareceria cotizado.
     */
    public function test_no_suma_totales_guardados_obsoletos(): void
    {
        $diez = Cotizacion::query()->findOrFail(10);
        $this->assertEqualsWithDelta(579641.85, (float) $diez->aiu_valortotal_con_iva, 0.01, 'El dato de partida cambio.');

        $cifras = $this->cifras();
        $sumaDeColumnas = (float) Cotizacion::query()->sum('aiu_valortotal_con_iva');

        $this->assertNotEqualsWithDelta($sumaDeColumnas, $cifras['valores']['total'], 1.0);
    }

    /** Los estados suman todas las cotizaciones y salen del flujo de aprobacion. */
    public function test_cada_cotizacion_cae_en_un_estado(): void
    {
        $cifras = $this->cifras();

        $this->assertSame(
            $cifras['conteos']['total'],
            array_sum(array_column($cifras['por_estado'], 'cantidad')),
        );

        $aprobadasEnBase = Cotizacion::query()->get()
            ->filter(fn (Cotizacion $c): bool => EstadoAprobacion::desdeColumnas($c->aprobacion1, $c->aprobacion2) === EstadoAprobacion::Aprobada)
            ->count();

        $this->assertSame($aprobadasEnBase, $cifras['aprobacion']['aprobadas']);
    }

    /** Los doce meses aparecen, tambien los vacios, y suman el año. */
    public function test_el_año_tiene_doce_meses_que_suman_el_total_del_año(): void
    {
        $cifras = $this->cifras();

        $this->assertCount(12, $cifras['por_mes']);
        $this->assertSame($cifras['conteos']['anio'], array_sum(array_column($cifras['por_mes'], 'cantidad')));
        $this->assertEqualsWithDelta($cifras['valores']['anio'], array_sum(array_column($cifras['por_mes'], 'valor')), 0.01);
    }

    /** "PAREX " y "PAREX" son el mismo cliente. */
    public function test_los_clientes_se_agrupan_sin_espacios_de_mas(): void
    {
        $clientes = array_column($this->cifras()['clientes'], 'cliente');

        $this->assertLessThanOrEqual(5, count($clientes));
        $this->assertSame(
            count($clientes),
            count(array_unique(array_map(fn (string $c): string => mb_strtoupper(trim($c)), $clientes))),
        );

        foreach ($clientes as $cliente) {
            $this->assertSame(trim($cliente), $cliente);
        }
    }

    /** Las cotizaciones con recursos sin calcular quedan señaladas: la 47 tiene un componente en -1. */
    public function test_señala_las_cotizaciones_con_avisos_del_calculo(): void
    {
        $ids = array_column($this->cifras()['con_avisos'], 'id');

        $this->assertContains(47, $ids);
        $this->assertNotContains(55, $ids, 'La 55 no tiene avisos.');
    }

    /** Un gerente solo ve lo de su gerencia, tambien aqui. */
    public function test_respeta_el_alcance_por_gerencia(): void
    {
        $gerente = $this->usuario('Gerente');

        $visibles = (new AlcanceDeCotizaciones)
            ->aplicar(Cotizacion::query(), $gerente)
            ->count();

        $this->assertSame($visibles, $this->cifras($gerente)['conteos']['total']);
        $this->assertLessThan(Cotizacion::query()->count(), $visibles);
    }

    /** Las cifras se guardan una hora y "Actualizar ahora" las rehace. */
    public function test_se_guardan_en_cache_y_se_pueden_olvidar(): void
    {
        Cache::flush();
        $admin = $this->usuario();
        $estadisticas = Estadisticas::crear();

        $primera = $estadisticas->para($admin);
        Cotizacion::query()->where('id', 55)->delete();

        $this->assertSame($primera, $estadisticas->para($admin), 'Deberia salir de la cache.');

        $estadisticas->olvidar($admin);
        $this->assertSame($primera['conteos']['total'] - 1, $estadisticas->para($admin)['conteos']['total']);
    }
}
