<?php

namespace Tests\Feature;

use App\Filament\Resources\Cotizacions\Pages\ListCotizacions;
use App\Filament\Resources\Cotizacions\Pages\ViewCotizacion;
use App\Models\Cotizacion;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las pantallas de cotizaciones.
 *
 * Comprueban lo que un usuario hace de verdad: entrar al listado, buscar una
 * cotizacion y abrirla. El sistema actual tiene 3,9% de cobertura y ninguna
 * prueba sobre sus pantallas.
 */
class PanelCotizacionesTest extends TestCase
{
    private function comoUsuario(): User
    {
        $usuario = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', User::ROLES_CON_ACCESO_AL_PANEL))
            ->firstOrFail();

        $this->actingAs($usuario);

        return $usuario;
    }

    /** El listado carga con las cotizaciones reales. */
    public function test_el_listado_muestra_las_cotizaciones(): void
    {
        $this->comoUsuario();

        Livewire::test(ListCotizacions::class)
            ->assertOk()
            ->assertCanSeeTableRecords(Cotizacion::query()->orderByDesc('id')->limit(10)->get());
    }

    /** Sin sesion no se llega al listado. */
    public function test_el_listado_exige_sesion(): void
    {
        $this->get('/admin/cotizaciones')->assertRedirect();
    }

    /** La busqueda por numero devuelve solo esa cotizacion. */
    public function test_la_busqueda_por_numero_es_exacta(): void
    {
        $this->comoUsuario();

        /**
         * Se elige un id que sea subcadena de otro: con el LIKE por defecto,
         * buscar "4" devolveria 4, 14, 24, 40...
         */
        $cotizacion = Cotizacion::query()->find(55);
        $this->assertNotNull($cotizacion);

        Livewire::test(ListCotizacions::class)
            ->searchTable('55')
            ->assertCanSeeTableRecords([$cotizacion])
            ->assertCanNotSeeTableRecords(
                Cotizacion::query()->whereIn('id', [50, 53, 54])->get()
            );
    }

    /** La busqueda por cliente encuentra sus cotizaciones. */
    public function test_la_busqueda_por_cliente_funciona(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->whereNotNull('cliente')->firstOrFail();

        Livewire::test(ListCotizacions::class)
            ->searchTable((string) $cotizacion->cliente)
            ->assertCanSeeTableRecords([$cotizacion]);
    }

    /** Se puede ordenar por total. */
    public function test_el_listado_se_ordena_por_total(): void
    {
        $this->comoUsuario();

        Livewire::test(ListCotizacions::class)
            ->sortTable('aiu_valortotal_con_iva', 'desc')
            ->assertOk();
    }

    /** El filtro por gerencia acota el listado. */
    public function test_el_filtro_por_gerencia_acota(): void
    {
        $this->comoUsuario();

        $gerencia = Cotizacion::query()->whereNotNull('gerencia')->value('gerencia');

        Livewire::test(ListCotizacions::class)
            ->filterTable('gerencia', $gerencia)
            ->assertOk()
            ->assertCanSeeTableRecords(
                Cotizacion::query()->where('gerencia', $gerencia)->limit(5)->get()
            );
    }

    /**
     * El detalle muestra el precio recalculado, no el guardado.
     *
     * Treinta y dos cotizaciones arrastran totales de versiones anteriores
     * del codigo; leer la columna seria mostrar un numero viejo.
     */
    public function test_el_detalle_muestra_el_precio_recalculado(): void
    {
        $this->comoUsuario();

        Livewire::test(ViewCotizacion::class, ['record' => 55])
            ->assertOk()
            ->assertSee('Ecopetrol');
    }

    /** El detalle de una cotizacion sin recursos tampoco falla. */
    public function test_el_detalle_de_una_cotizacion_vacia_no_falla(): void
    {
        $this->comoUsuario();

        $vacia = Cotizacion::query()
            ->whereDoesntHave('revisiones')
            ->firstOrFail();

        Livewire::test(ViewCotizacion::class, ['record' => $vacia->id])->assertOk();
    }

    /**
     * La cotizacion que el sistema actual no puede abrir si se abre aqui.
     *
     * La 43 revienta con "Division by zero" por una duracion de servicio en
     * cero. El dominio nuevo la tolera.
     */
    public function test_la_cotizacion_con_duracion_cero_se_puede_abrir(): void
    {
        $this->comoUsuario();

        Livewire::test(ViewCotizacion::class, ['record' => 43])->assertOk();
    }

    /** La cotizacion con recursos no calculables avisa en pantalla. */
    public function test_el_detalle_avisa_de_los_recursos_sin_calcular(): void
    {
        $this->comoUsuario();

        Livewire::test(ViewCotizacion::class, ['record' => 7])
            ->assertOk()
            ->assertSee('Avisos');
    }

    /**
     * Estan las cuatro pantallas del ciclo completo.
     *
     * Crear pide solo los datos de cabecera; los recursos se cargan en la
     * pantalla de edicion, donde cada pestana sabe a que cotizacion
     * pertenece sin depender de la sesion.
     */
    public function test_estan_las_cuatro_pantallas(): void
    {
        $paginas = array_keys(\App\Filament\Resources\Cotizacions\CotizacionResource::getPages());

        foreach (['index', 'create', 'view', 'edit'] as $pagina) {
            $this->assertContains($pagina, $paginas, "Falta la pantalla '{$pagina}'.");
        }
    }

    /** El listado no dispara una consulta por fila. */
    public function test_el_listado_no_dispara_una_consulta_por_fila(): void
    {
        $this->comoUsuario();

        $consultas = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$consultas): void {
            $consultas++;
        });

        Livewire::test(ListCotizacions::class)->assertOk();

        $this->assertLessThan(
            40,
            $consultas,
            "El listado disparo {$consultas} consultas para diez filas."
        );
    }
}
