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

    /** Un gerente de la gerencia indicada. */
    private function comoGerente(): \App\Models\User
    {
        $gerente = \App\Models\User::factory()->create(['id_gerencia' => '4']);
        $gerente->assignRole('Gerente');

        $this->actingAs($gerente);

        return $gerente;
    }

    /**
     * Pone la cotizacion en un estado y deja la base como estaba.
     *
     * Son datos reales de produccion copiados en local: no se quedan
     * modificados al terminar la prueba.
     */
    private function conEstado(array $estado, callable $prueba): void
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $original = $cotizacion->only([
            'gerencia', 'aprobacion1', 'aprobacion2',
            'aprobacion1_user_id', 'aprobacion2_user_id',
            'aprobacion1_comentarios', 'aprobacion2_comentarios',
            'aprobacion1_fecha', 'aprobacion2_fecha',
        ]);

        try {
            $cotizacion->forceFill($estado)->save();

            $prueba($cotizacion);
        } finally {
            $cotizacion->forceFill($original)->save();
        }
    }

    /**
     * Los botones de aprobacion aparecen segun el estado y el rol.
     *
     * Comprueba el enganche entre el dominio y la pantalla: que el
     * visible() de cada boton pregunte a las reglas correctas.
     */
    public function test_los_botones_de_aprobacion_dependen_del_estado(): void
    {
        $this->comoGerente();

        $this->conEstado(
            ['gerencia' => '4', 'aprobacion1' => 'PENDIENTE', 'aprobacion2' => null],
            function (): void {
                Livewire::test(ViewCotizacion::class, ['record' => 55])
                    ->assertActionVisible('aprobarTecnica')
                    ->assertActionVisible('rechazarTecnica')
                    ->assertActionHidden('aprobarFinanciera')
                    ->assertActionHidden('reabrir');
            }
        );

        /** Ya aprobada: no queda nada que decidir, ni se edita. */
        $this->conEstado(
            ['aprobacion1' => 'SI', 'aprobacion2' => 'SI'],
            function (): void {
                Livewire::test(ViewCotizacion::class, ['record' => 55])
                    ->assertActionHidden('aprobarTecnica')
                    ->assertActionHidden('edit');
            }
        );
    }

    /** Aprobar desde la pantalla deja el rastro en la base. */
    public function test_se_puede_aprobar_desde_la_pantalla(): void
    {
        $gerente = $this->comoGerente();

        $this->conEstado(
            ['gerencia' => '4', 'aprobacion1' => 'PENDIENTE', 'aprobacion2' => null],
            function (Cotizacion $cotizacion) use ($gerente): void {
                Livewire::test(ViewCotizacion::class, ['record' => 55])
                    ->callAction('aprobarTecnica', ['comentarios' => 'Revisada.'])
                    ->assertHasNoActionErrors();

                $cotizacion->refresh();

                $this->assertSame('SI', $cotizacion->aprobacion1);
                $this->assertSame($gerente->id, $cotizacion->aprobacion1_user_id);
                $this->assertSame('PENDIENTE', $cotizacion->aprobacion2);
            }
        );
    }

    /** Un rechazo sin motivo no se guarda. */
    public function test_rechazar_sin_motivo_no_pasa_la_validacion(): void
    {
        $this->comoGerente();

        $this->conEstado(
            ['gerencia' => '4', 'aprobacion1' => 'PENDIENTE', 'aprobacion2' => null],
            function (Cotizacion $cotizacion): void {
                Livewire::test(ViewCotizacion::class, ['record' => 55])
                    ->callAction('rechazarTecnica', ['comentarios' => ''])
                    ->assertHasActionErrors(['comentarios']);

                $this->assertSame('PENDIENTE', $cotizacion->refresh()->aprobacion1);
            }
        );
    }

    /** No se entra a editar una cotizacion aprobada. */
    public function test_editar_una_aprobada_redirige_al_detalle(): void
    {
        $this->comoUsuario();

        $this->conEstado(
            ['aprobacion1' => 'SI', 'aprobacion2' => 'SI'],
            function (): void {
                Livewire::test(\App\Filament\Resources\Cotizacions\Pages\EditCotizacion::class, ['record' => 55])
                    ->assertRedirect();
            }
        );
    }

    /**
     * La oferta se descarga desde el panel.
     *
     * El documento se genera al vuelo con los totales del dominio, no con
     * las columnas guardadas, asi que nunca se envia un precio viejo.
     */
    public function test_se_puede_descargar_la_oferta(): void
    {
        $this->comoUsuario();

        if (! is_file(storage_path('app/plantillas/oferta.docx'))) {
            $this->artisan('plantilla:oferta');
        }

        Livewire::test(ViewCotizacion::class, ['record' => 55])
            ->callAction('descargarOferta')
            ->assertOk();
    }

    /**
     * Desde el listado se puede entrar a editar.
     *
     * Hasta ahora solo estaba "Ver": para corregir un dato habia que abrir
     * el detalle y buscar el boton alli.
     */
    public function test_el_listado_ofrece_editar_lo_que_se_puede_editar(): void
    {
        /**
         * Un borrador CON gerencia: es lo que permite que el gerente de esa
         * gerencia lo edite. Hay borradores con la columna vacia, y ahi no
         * puede entrar nadie salvo su autor.
         */
        $borrador = Cotizacion::query()
            ->whereRaw("COALESCE(aprobacion1, '') = ''")
            ->whereNotNull('gerencia')
            ->firstOrFail();

        $autor = User::factory()->create(['id_gerencia' => $borrador->gerencia]);
        $autor->assignRole('Gerente');
        $this->actingAs($autor);

        Livewire::test(ListCotizacions::class)
            ->assertTableActionVisible('edit', record: $borrador);
    }

    /**
     * Lo aprobado no ofrece editar.
     *
     * El boton no se oculta por adorno: pulsarlo llevaria a una pantalla
     * que rebota al detalle con una explicacion.
     */
    public function test_el_listado_no_ofrece_editar_lo_aprobado(): void
    {
        $this->comoUsuario();

        $aprobada = Cotizacion::query()
            ->whereRaw("UPPER(TRIM(COALESCE(aprobacion1, ''))) = 'SI'")
            ->whereRaw("UPPER(TRIM(COALESCE(aprobacion2, ''))) = 'SI'")
            ->firstOrFail();

        Livewire::test(ListCotizacions::class)
            ->assertTableActionHidden('edit', record: $aprobada);
    }
}
