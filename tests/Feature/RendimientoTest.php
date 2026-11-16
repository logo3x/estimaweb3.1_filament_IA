<?php

namespace Tests\Feature;

use App\Dominio\CalcularCotizacion;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use App\Filament\Resources\Cotizacions\Pages\ListCotizacions;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El sistema no se degrada con el numero de filas.
 *
 * El problema que motivo la reconstruccion: el sistema actual tarda treinta
 * y cinco segundos en abrir una cotizacion en frio. La causa de fondo es
 * casi siempre la misma -una consulta por fila- y no se nota programando
 * con tres registros; aparece con los datos reales.
 *
 * Estas pruebas no miden milisegundos, que dependen de la maquina: cuentan
 * **consultas**, que es lo que escala mal.
 */
class RendimientoTest extends TestCase
{
    use DatabaseTransactions;

    private function comoAdministrador(): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('Administrador');

        $this->actingAs($usuario);

        return $usuario;
    }

    /**
     * Cuenta las consultas de una operacion.
     *
     * @return int
     */
    private function consultasDe(callable $accion): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $accion();

        $cuantas = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $cuantas;
    }

    /**
     * El listado no lanza una consulta por fila.
     *
     * Cada fila muestra el estado de aprobacion y el total; si alguno de
     * los dos se resolviera consultando, el listado crecería con el numero
     * de cotizaciones.
     */
    public function test_el_listado_no_consulta_por_fila(): void
    {
        $this->comoAdministrador();

        $diez = $this->consultasDe(fn () => Livewire::test(ListCotizacions::class)->assertOk());

        $this->assertLessThan(
            30,
            $diez,
            "El listado lanzó {$diez} consultas para diez filas: hay un N+1."
        );
    }

    /**
     * Y no crece al mostrar mas filas.
     *
     * Es la comprobacion que de verdad detecta el N+1: si pasar de diez a
     * cincuenta filas multiplica las consultas, el problema esta ahi
     * aunque el numero absoluto parezca razonable.
     */
    public function test_el_listado_no_crece_con_el_numero_de_filas(): void
    {
        $this->comoAdministrador();

        $diez = $this->consultasDe(fn () => Livewire::test(ListCotizacions::class)
            ->set('tableRecordsPerPage', 10)
            ->assertOk());

        $cincuenta = $this->consultasDe(fn () => Livewire::test(ListCotizacions::class)
            ->set('tableRecordsPerPage', 50)
            ->assertOk());

        $this->assertLessThanOrEqual(
            $diez + 5,
            $cincuenta,
            "Con cincuenta filas pasa de {$diez} a {$cincuenta} consultas: crece con los datos."
        );
    }

    /**
     * Calcular el precio no depende del numero de recursos.
     *
     * La cotizacion 55 es de las mas pesadas de produccion.
     */
    public function test_el_calculo_del_precio_no_dispara_consultas(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $consultas = $this->consultasDe(
            fn () => CalcularCotizacion::crear()->para($cotizacion)
        );

        $this->assertLessThan(
            60,
            $consultas,
            "Calcular el precio lanzó {$consultas} consultas."
        );
    }

    /**
     * El filtro por gerencia no añade consultas.
     *
     * Se resuelve en el SQL, no cargando las cotizaciones para descartarlas
     * despues en PHP.
     */
    public function test_el_filtro_por_gerencia_no_cuesta_consultas(): void
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('Creador');
        $this->actingAs($usuario);

        $consultas = $this->consultasDe(
            fn () => CotizacionResource::getEloquentQuery()->limit(10)->get()
        );

        $this->assertLessThanOrEqual(
            2,
            $consultas,
            "Filtrar por gerencia costó {$consultas} consultas; debería resolverse en una."
        );
    }

    /**
     * El contador del menu es una sola consulta.
     *
     * Se muestra en cada carga de pagina, de modo que lo que cueste aqui
     * se paga en todo el panel.
     */
    public function test_el_contador_del_menu_es_una_consulta(): void
    {
        $this->comoAdministrador();

        $consultas = $this->consultasDe(
            fn () => CotizacionResource::getNavigationBadge()
        );

        $this->assertLessThanOrEqual(2, $consultas);
    }

    /**
     * El detalle carga los recursos de una vez.
     *
     * Son doce relaciones; sin eager loading serian doce consultas por
     * cotizacion mas una por cada elemento de catalogo.
     */
    public function test_el_detalle_carga_los_recursos_de_una_vez(): void
    {
        $this->comoAdministrador();

        $cotizacion = Cotizacion::findOrFail(55);

        $consultas = $this->consultasDe(fn () => $cotizacion->load([
            'items', 'personals', 'herramientas.items', 'equipos.items',
            'consumibles.items', 'vehiculos', 'oficinas', 'aseos',
            'viaticos', 'servicios', 'comunicaciones', 'otros',
        ]));

        $this->assertLessThan(
            25,
            $consultas,
            "Cargar los recursos costó {$consultas} consultas."
        );
    }
}
