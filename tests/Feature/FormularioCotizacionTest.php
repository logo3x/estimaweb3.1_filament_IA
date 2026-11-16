<?php

namespace Tests\Feature;

use App\Filament\Resources\Cotizacions\Pages\CreateCotizacion;
use App\Filament\Resources\Cotizacions\Pages\EditCotizacion;
use App\Filament\Resources\Cotizacions\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Cotizacions\RelationManagers\PersonalsRelationManager;
use App\Filament\Resources\Cotizacions\RelationManagers\ViaticosRelationManager;
use App\Models\Cotizacion;
use App\Models\Turno;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El formulario de cotizaciones: crear, editar y cargar recursos.
 *
 * Es la parte mas grande del sistema (5.876 lineas en el actual) y la que no
 * tenia ninguna prueba.
 */
class FormularioCotizacionTest extends TestCase
{
    /**
     * Un usuario que SI puede editar la cotizacion de las pruebas.
     *
     * La 55 esta esperando aprobacion tecnica, y en ese estado solo la toca
     * el gerente de su gerencia. Antes estas pruebas tomaban al primer
     * usuario con acceso al panel y daban por hecho que podria editar
     * cualquier cosa; ahora el flujo de aprobacion lo impide, que es
     * justamente lo que debe hacer.
     */
    private function comoUsuario(): User
    {
        $gerencia = Cotizacion::query()->whereKey(55)->value('gerencia');

        $usuario = User::factory()->create(['id_gerencia' => $gerencia]);
        $usuario->assignRole('Gerente');

        $this->actingAs($usuario);

        return $usuario;
    }

    /** @return array<string, mixed> */
    private function datosValidos(): array
    {
        return [
            'cliente' => 'Cliente de prueba',
            'codigo' => 'PRUEBA-' . uniqid(),
            'cargo' => 'Jefe de mantenimiento',
            'referencia' => 'REF-001',
            'nombre' => 'Servicio de prueba',
            'alcance' => 'Alcance de prueba',
            'observaciones' => 'Observaciones de prueba',
            'vigencia' => '30 días',
            'forma_pago' => '30 días después de factura',
            'dias_dura_servicio' => 30,
            'estimado_inicio' => now()->addMonth()->format('Y-m-d'),
        ];
    }

    /** Se crea una cotizacion con los datos de cabecera. */
    public function test_se_puede_crear_una_cotizacion(): void
    {
        $usuario = $this->comoUsuario();
        $datos = $this->datosValidos();

        Livewire::test(CreateCotizacion::class)
            ->fillForm($datos)
            ->call('create')
            ->assertHasNoFormErrors();

        $creada = Cotizacion::query()->where('codigo', $datos['codigo'])->first();

        $this->assertNotNull($creada, 'No se creo la cotizacion.');

        /** Queda registrado quien la creo: decide quien puede editarla. */
        $this->assertSame($usuario->id, $creada->id_user);
        $this->assertSame('En elaboración', $creada->estado);

        $creada->delete();
    }

    /**
     * La duracion no puede ser cero.
     *
     * Se copia a cada EPP y examen, donde divide su costo. Un cero hacia que
     * la cotizacion entera no se pudiera abrir en el sistema actual.
     */
    public function test_la_duracion_debe_ser_al_menos_un_dia(): void
    {
        $this->comoUsuario();

        Livewire::test(CreateCotizacion::class)
            ->fillForm([...$this->datosValidos(), 'dias_dura_servicio' => 0])
            ->call('create')
            ->assertHasFormErrors(['dias_dura_servicio']);
    }

    /** Los campos obligatorios se exigen. */
    public function test_el_formulario_exige_los_campos_obligatorios(): void
    {
        $this->comoUsuario();

        Livewire::test(CreateCotizacion::class)
            ->fillForm([
                'cliente' => '',
                'codigo' => '',
                'nombre' => '',
                'dias_dura_servicio' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['cliente', 'codigo', 'nombre', 'dias_dura_servicio']);
    }

    /**
     * El flujo de aprobacion cierra la pantalla de edicion.
     *
     * Quien no tiene permiso en el estado actual no entra: se le redirige
     * al detalle con el motivo. En el sistema actual la pantalla se abre
     * igual y el bloqueo solo aparece al intentar guardar.
     *
     * El usuario es de la MISMA gerencia a proposito: de otra ni siquiera
     * encontraria la cotizacion, y se estaria probando el filtro por
     * gerencia en vez del flujo de aprobacion.
     */
    public function test_quien_no_tiene_permiso_no_entra_a_editar(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $sinPermiso = User::factory()->create(['id_gerencia' => $cotizacion->gerencia]);
        $sinPermiso->assignRole('Creador');
        $this->actingAs($sinPermiso);

        /** La 55 espera aprobacion tecnica: ahi solo entra su gerente. */
        Livewire::test(EditCotizacion::class, ['record' => 55])->assertRedirect();
    }

    /**
     * Lo de otra gerencia no existe para quien no es de ella.
     *
     * No se redirige con una explicacion: se responde que no hay nada. Un
     * mensaje de "no puede editar esta" ya confirmaria que existe.
     */
    public function test_lo_de_otra_gerencia_no_aparece(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);

        /**
         * Una gerencia que exista en el catalogo: la columna de las
         * cotizaciones guarda valores que ya no estan ahi -hay una con
         * gerencia '2'-, y los usuarios tienen clave foranea.
         */
        $otraGerencia = (string) \App\Models\Gerencia::query()
            ->where('id', '!=', $cotizacion->gerencia)
            ->value('id');

        $ajeno = User::factory()->create(['id_gerencia' => $otraGerencia]);
        $ajeno->assignRole('Creador');
        $this->actingAs($ajeno);

        $this->get(
            \App\Filament\Resources\Cotizacions\CotizacionResource::getUrl('edit', ['record' => 55])
        )->assertNotFound();
    }

    /** La pantalla de edicion carga con los datos de la cotizacion. */
    public function test_la_edicion_carga_los_datos(): void
    {
        $this->comoUsuario();

        Livewire::test(EditCotizacion::class, ['record' => 55])
            ->assertOk()
            ->assertFormSet([
                'cliente' => 'Ecopetrol',
                'dias_dura_servicio' => 30,
            ]);
    }

    /** Se pueden guardar cambios de cabecera. */
    public function test_se_pueden_guardar_los_datos_de_cabecera(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->findOrFail(55);
        $original = $cotizacion->observaciones;

        Livewire::test(EditCotizacion::class, ['record' => 55])
            ->fillForm(['observaciones' => 'Observación de prueba'])
            ->call('save')
            ->assertHasNoFormErrors();

        /**
         * El editor enriquecido envuelve en parrafos.
         *
         * Las observaciones van al Word con su formato -titulos, listas-,
         * asi que se guardan como HTML. Es como lo hace el sistema actual:
         * en los datos reales hay `<p>...</p>`.
         */
        $this->assertStringContainsString(
            'Observación de prueba',
            (string) $cotizacion->fresh()->observaciones
        );

        $cotizacion->update(['observaciones' => $original]);
    }

    /**
     * Recalcular guarda los totales con el dominio nuevo.
     *
     * El listado lee las columnas guardadas; esta accion las pone al dia.
     */
    public function test_recalcular_actualiza_los_totales(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->findOrFail(55);
        $originales = $cotizacion->only([
            'valor_total', 'valor_total_con_aiu', 'aiu_valortotal_con_iva',
            'aiu_administracion', 'aiu_imprevistos', 'aiu_utilidad', 'aiu_iva',
        ]);

        Livewire::test(EditCotizacion::class, ['record' => 55])
            ->callAction('recalcular');

        $actualizada = $cotizacion->fresh();

        $this->assertGreaterThan(0, $actualizada->aiu_valortotal_con_iva);

        /** El subtotal es costo directo mas AIU. */
        $this->assertEqualsWithDelta(
            $actualizada->valor_total + $actualizada->aiu_administracion
                + $actualizada->aiu_imprevistos + $actualizada->aiu_utilidad,
            $actualizada->valor_total_con_aiu,
            1.0
        );

        $cotizacion->update($originales);
    }

    /**
     * Las trece pestanas: doce de recursos mas la de asignacion.
     *
     * El orden importa: los items primero, porque sin ellos no hay a que
     * asignar; la asignacion al final, porque reparte lo que las anteriores
     * cargaron.
     */
    public function test_las_pestanas_siguen_el_orden_del_sistema_actual(): void
    {
        $relaciones = \App\Filament\Resources\Cotizacions\CotizacionResource::getRelations();

        /**
         * Siete: items, personal, los tres activos que se amortizan, las
         * categorias adicionales -las siete menores bajo una sola pestaña-
         * y la asignacion.
         */
        $this->assertCount(7, $relaciones);

        $this->assertInstanceOf(
            \Filament\Resources\RelationManagers\RelationGroup::class,
            $relaciones[5],
            'Las categorías menores deberían ir bajo una sola pestaña.'
        );

        $this->assertSame(ItemsRelationManager::class, $relaciones[0]);
        $this->assertSame(PersonalsRelationManager::class, $relaciones[1]);

        $this->assertSame(
            \App\Filament\Resources\Cotizacions\RelationManagers\RevisionesRelationManager::class,
            end($relaciones),
            'La asignación debe ir al final: reparte lo que las demás pestañas cargaron.'
        );
    }


    /**
     * Dentro de "Categorias adicionales" estan las siete.
     *
     * Cada una en su seccion plegada: abiertas de golpe son siete tablas
     * apiladas y la pagina no acaba.
     */
    public function test_el_grupo_de_categorias_lleva_las_siete(): void
    {
        $grupo = \App\Filament\Resources\Cotizacions\RelationManagers\CategoriasAdicionales::grupo();

        $this->assertCount(7, $grupo->getManagers());
        $this->assertSame('Categorías adicionales', $grupo->getLabel());
    }

    /** Cada categoria tiene su titulo y su icono. */
    public function test_cada_categoria_tiene_titulo_propio(): void
    {
        for ($i = 0; $i < 7; $i++) {
            [$titulo, $icono] = \App\Filament\Resources\Cotizacions\RelationManagers\CategoriasAdicionales::etiqueta($i);

            $this->assertNotSame('Categoría', $titulo, "La categoría {$i} no tiene título propio.");
            $this->assertStringStartsWith('heroicon-', $icono);
        }
    }

    /** El gestor de items carga los de la cotizacion. */
    public function test_el_gestor_de_items_carga(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->with('items')->findOrFail(55);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords($cotizacion->items->take(10));
    }

    /** Se puede anadir un item. */
    public function test_se_puede_anadir_un_item(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->findOrFail(55);
        $antes = $cotizacion->items()->count();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->callTableAction('create', data: [
                'numero' => 99,
                'nombre' => 'Ítem de prueba',
                'unidad' => 'Und',
                'cantidad' => 1,
            ]);

        $this->assertSame($antes + 1, $cotizacion->items()->count());

        $cotizacion->items()->where('nombre', 'Ítem de prueba')->delete();
    }

    /** El gestor de personal carga y calcula el costo de cada fila. */
    public function test_el_gestor_de_personal_carga(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()
            ->whereHas('personals')
            ->with('personals')
            ->firstOrFail();

        Livewire::test(PersonalsRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords($cotizacion->personals->take(5));
    }

    /** Se puede anadir una persona con su turno. */
    public function test_se_puede_anadir_personal(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->findOrFail(55);
        $turno = Turno::query()->firstOrFail();
        $antes = $cotizacion->personals()->count();

        Livewire::test(PersonalsRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->callTableAction('create', data: [
                'nombre' => 'Persona de prueba',
                'rol' => 'Rol Mensual',
                'id_turno' => $turno->id,
                'salario' => 2_000_000,
                'auxiliosin' => 0,
                'capacitaciones' => 0,
                'p_he' => 0,
                'hedo' => 0, 'heno' => 0, 'hedf' => 0, 'henf' => 0,
                'donfesoca' => 0, 'donhabitual' => 0, 'rn' => 0,
                'f_prestacional' => false,
                'aux_educacion' => false,
            ]);

        $this->assertSame($antes + 1, $cotizacion->personals()->count());

        $cotizacion->personals()->where('nombre', 'Persona de prueba')->delete();
    }

    /** Un recurso simple se puede anadir. */
    public function test_se_puede_anadir_un_recurso_simple(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->findOrFail(55);
        $antes = $cotizacion->viaticos()->count();

        Livewire::test(ViaticosRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->callTableAction('create', data: [
                'nombre' => 'Viático de prueba',
                'unidad' => 'Dia',
                'cantidad' => 50000,
            ]);

        $this->assertSame($antes + 1, $cotizacion->viaticos()->count());

        $cotizacion->viaticos()->where('nombre', 'Viático de prueba')->delete();
    }

    /**
     * Clonar copia el elemento con otro nombre.
     *
     * Es lo que mas se usa: una cuadrilla son varias personas con el mismo
     * perfil, y volver a escribirlas entera es el principal motivo de queja
     * sobre el sistema actual.
     */
    public function test_se_puede_clonar_un_recurso(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->whereHas('viaticos')->firstOrFail();
        $original = $cotizacion->viaticos()->firstOrFail();
        $antes = $cotizacion->viaticos()->count();

        Livewire::test(ViaticosRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->callTableAction('replicate', $original);

        $this->assertSame($antes + 1, $cotizacion->viaticos()->count());

        $copia = $cotizacion->viaticos()
            ->where('nombre', $original->nombre . ' (copia)')
            ->first();

        $this->assertNotNull($copia, 'La copia no conserva el nombre esperado.');
        $this->assertSame((int) $original->cantidad, (int) $copia->cantidad);

        $copia->delete();
    }

    /** Los recursos de una cotizacion no se mezclan con los de otra. */
    public function test_cada_pestana_solo_ve_los_recursos_de_su_cotizacion(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::query()->whereHas('personals')->firstOrFail();

        $ajenos = \App\Models\Personal::query()
            ->where('id_cotizacion', '!=', $cotizacion->id)
            ->limit(5)
            ->get();

        $this->assertNotEmpty($ajenos);

        Livewire::test(PersonalsRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->assertCanNotSeeTableRecords($ajenos);
    }





    /**
     * Guardar la cabecera NO puede alterar el AIU.
     *
     * Fallo real: las banderas se guardan como texto ('SI'/'NO') y el
     * interruptor las recibia sin convertir. En PHP toda cadena no vacia
     * es verdadera, de modo que 'NO' se cargaba como activado y al guardar
     * quedaba en 'SI'. Cambiar una observacion invertia el escenario del
     * AIU y **con el, el precio final de la cotizacion**, sin aviso.
     *
     * Lo detecto el golden master de la cotizacion 55: 713 millones
     * pasaron a 607.
     */
    public function test_guardar_la_cabecera_no_altera_el_aiu(): void
    {
        $this->comoUsuario();

        $copia = Cotizacion::findOrFail(55)->replicate(['id']);
        $copia->codigo = 'INTACTO-' . uniqid();
        $copia->save();

        $copia->aiu()->create([
            'contrato_obra' => 'NO',
            'mostrar_aiu' => 'NO',
            'contrato_actual' => 'NO',
            'administracion' => 5.78,
            'imprevistos' => 0.5,
            'utilidad' => 8,
        ]);

        try {
            Livewire::test(EditCotizacion::class, ['record' => $copia->id])
                ->fillForm(['observaciones' => 'Solo cambio esto'])
                ->call('save')
                ->assertHasNoFormErrors();

            $aiu = $copia->aiu()->first();

            $this->assertSame('NO', $aiu->contrato_obra, 'Guardar la cabecera activó "contrato de obra".');
            $this->assertSame('NO', $aiu->mostrar_aiu, 'Guardar la cabecera activó "mostrar AIU".');
            $this->assertSame(
                'servicio/aiu-oculto',
                \App\Dominio\Aiu\ParametrosAiu::desdeModelo($aiu)->escenario(),
                'Cambió el escenario del AIU, y con él el precio.'
            );
        } finally {
            $copia->aiu()->delete();
            $copia->delete();
        }
    }
}
