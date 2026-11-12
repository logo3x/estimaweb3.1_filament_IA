<?php

namespace Tests\Feature;

use App\Dominio\CalcularCotizacion;
use App\Filament\Resources\Cotizacions\Pages\EditCotizacion;
use App\Filament\Resources\Cotizacions\RelationManagers\RevisionesRelationManager;
use App\Models\Cotizacion;
use App\Models\Personal;
use App\Models\Revision;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El reparto de recursos entre los items de la oferta.
 *
 * Es la pieza que convierte recursos cargados en precio: el motor recorre
 * estas filas, y un recurso que no este aqui no cuenta por bien cargado que
 * este.
 */
class AsignacionRecursosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(
            User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', User::ROLES_CON_ACCESO_AL_PANEL))
                ->firstOrFail()
        );
    }

    private function gestorDe(Cotizacion $cotizacion)
    {
        return Livewire::test(RevisionesRelationManager::class, [
            'ownerRecord' => $cotizacion,
            'pageClass' => EditCotizacion::class,
        ]);
    }

    /** El gestor carga las asignaciones de la cotizacion. */
    public function test_el_gestor_carga_las_asignaciones(): void
    {
        $cotizacion = Cotizacion::query()->whereHas('revisiones')->firstOrFail();

        $this->gestorDe($cotizacion)
            ->assertOk()
            ->assertCanSeeTableRecords($cotizacion->revisiones()->limit(5)->get());
    }

    /** Solo se ven las asignaciones de esta cotizacion. */
    public function test_no_se_ven_las_asignaciones_de_otra_cotizacion(): void
    {
        $cotizacion = Cotizacion::query()->whereHas('revisiones')->firstOrFail();

        $ajenas = Revision::query()
            ->where('id_cotizacion', '!=', $cotizacion->id)
            ->limit(5)
            ->get();

        $this->assertNotEmpty($ajenas);

        $this->gestorDe($cotizacion)->assertCanNotSeeTableRecords($ajenas);
    }

    /**
     * Asignar un recurso lo hace contar en el precio.
     *
     * Es la prueba que justifica esta pantalla: antes y despues de asignar,
     * el costo del item cambia.
     */
    public function test_asignar_un_recurso_lo_hace_contar_en_el_precio(): void
    {
        $cotizacion = Cotizacion::query()
            ->whereHas('items')
            ->whereHas('viaticos')
            ->with(['items', 'viaticos'])
            ->firstOrFail();

        $item = $cotizacion->items->first();
        $viatico = $cotizacion->viaticos->first();

        $antes = CalcularCotizacion::crear()->para($cotizacion);
        $costoAntes = $antes->costoPorItem[$item->id] ?? 0.0;

        $this->gestorDe($cotizacion)->callTableAction('create', data: [
            'id_item_cotizacion' => $item->id,
            'tipo_recurso' => 'viatico',
            'recurso_id' => $viatico->id,
            'unidad' => 'N/A',
            'cantidad' => 2,
        ]);

        $nueva = Revision::query()
            ->where('id_cotizacion', $cotizacion->id)
            ->where('id_viatico', $viatico->id)
            ->where('id_item_cotizacion', $item->id)
            ->where('cantidad', 2)
            ->first();

        $this->assertNotNull($nueva, 'No se creo la asignacion.');

        $despues = CalcularCotizacion::crear()->para($cotizacion->fresh());

        $this->assertGreaterThan(
            $costoAntes,
            $despues->costoPorItem[$item->id] ?? 0.0,
            'Asignar el recurso deberia aumentar el costo del item.'
        );

        $nueva->delete();
    }

    /**
     * Una asignacion apunta a UN solo recurso.
     *
     * Todo el calculo lo asume; si una fila tuviera dos, su costo se
     * contaria dos veces.
     */
    public function test_la_asignacion_apunta_a_un_solo_recurso(): void
    {
        $cotizacion = Cotizacion::query()
            ->whereHas('items')
            ->whereHas('viaticos')
            ->with(['items', 'viaticos'])
            ->firstOrFail();

        $this->gestorDe($cotizacion)->callTableAction('create', data: [
            'id_item_cotizacion' => $cotizacion->items->first()->id,
            'tipo_recurso' => 'viatico',
            'recurso_id' => $cotizacion->viaticos->first()->id,
            'unidad' => 'N/A',
            'cantidad' => 1,
        ]);

        $creada = Revision::query()
            ->where('id_cotizacion', $cotizacion->id)
            ->latest('id')
            ->firstOrFail();

        $apuntados = 0;

        foreach (array_keys(Revision::RECURSOS) as $columna) {
            if ($creada->{$columna} !== null) {
                $apuntados++;
            }
        }

        $this->assertSame(
            1,
            $apuntados,
            "La asignacion deberia apuntar a UN recurso, y apunta a {$apuntados}."
        );

        $creada->delete();
    }

    /**
     * Reasignar a otro recurso limpia el anterior.
     *
     * Sin eso quedaria una fila con dos recursos y el costo se duplicaria.
     */
    public function test_reasignar_limpia_el_recurso_anterior(): void
    {
        $cotizacion = Cotizacion::query()
            ->whereHas('personals')
            ->whereHas('viaticos')
            ->with(['personals', 'viaticos'])
            ->firstOrFail();

        $revision = new Revision;
        $revision->asignarRecurso($cotizacion->personals->first());

        $this->assertNotNull($revision->id_personal);

        $revision->asignarRecurso($cotizacion->viaticos->first());

        $this->assertNull($revision->id_personal, 'El recurso anterior no se limpio.');
        $this->assertNotNull($revision->id_viatico);
    }

    /**
     * EL FALLO QUE NO PUEDE REPETIRSE: las unidades ofrecidas son validas.
     *
     * El sistema actual acepta cualquier texto, de modo que "Persona" en un
     * recurso de personal hace que valga cero sin aviso. Aqui el desplegable
     * solo ofrece lo que el calculo entiende.
     */
    public function test_solo_se_ofrecen_unidades_que_el_calculo_entiende(): void
    {
        $cotizacion = Cotizacion::query()->whereHas('personals')->firstOrFail();

        $componente = $this->gestorDe($cotizacion);

        $reflexion = new \ReflectionMethod(RevisionesRelationManager::class, 'unidadesDe');
        $reflexion->setAccessible(true);

        /** Personal cobra por tiempo: tres unidades validas. */
        $delPersonal = array_keys($reflexion->invoke($componente->instance(), 'personal'));

        $this->assertSame(['Dia Habil', 'Dia Calendario', 'Mes'], $delPersonal);
        $this->assertNotContains('Persona', $delPersonal);
        $this->assertNotContains('N/A', $delPersonal);

        /** Los viaticos se cobran por cantidad: la unidad es una etiqueta. */
        $this->assertSame(['N/A'], array_keys($reflexion->invoke($componente->instance(), 'viatico')));
    }

    /**
     * Solo se ofrecen tipos con recursos cargados.
     *
     * Elegir "herramienta" sin herramientas cargadas lleva a un desplegable
     * vacio.
     */
    public function test_solo_se_ofrecen_tipos_con_recursos_cargados(): void
    {
        $cotizacion = Cotizacion::query()
            ->whereHas('personals')
            ->doesntHave('aseos')
            ->firstOrFail();

        $componente = $this->gestorDe($cotizacion);

        $reflexion = new \ReflectionMethod(RevisionesRelationManager::class, 'tiposDisponibles');
        $reflexion->setAccessible(true);

        $tipos = $reflexion->invoke($componente->instance());

        $this->assertArrayHasKey('personal', $tipos);
        $this->assertArrayNotHasKey('aseo', $tipos, 'Se ofrece un tipo sin recursos cargados.');
    }

    /** Solo se ofrecen recursos de esta cotizacion. */
    public function test_solo_se_ofrecen_recursos_de_esta_cotizacion(): void
    {
        $cotizacion = Cotizacion::query()->whereHas('personals')->firstOrFail();

        $componente = $this->gestorDe($cotizacion);

        $reflexion = new \ReflectionMethod(RevisionesRelationManager::class, 'recursosDe');
        $reflexion->setAccessible(true);

        $ofrecidos = array_keys($reflexion->invoke($componente->instance(), 'personal'));

        $propios = Personal::query()
            ->where('id_cotizacion', $cotizacion->id)
            ->pluck('id')
            ->all();

        sort($ofrecidos);
        sort($propios);

        $this->assertSame($propios, $ofrecidos);
    }

    /** Se puede borrar una asignacion. */
    public function test_se_puede_borrar_una_asignacion(): void
    {
        $cotizacion = Cotizacion::query()
            ->whereHas('items')
            ->whereHas('viaticos')
            ->with(['items', 'viaticos'])
            ->firstOrFail();

        $revision = new Revision([
            'id_cotizacion' => $cotizacion->id,
            'id_item_cotizacion' => $cotizacion->items->first()->id,
            'unidad' => 'N/A',
            'cantidad' => 1,
        ]);
        $revision->asignarRecurso($cotizacion->viaticos->first());
        $revision->save();

        $this->gestorDe($cotizacion)->callTableAction('delete', $revision);

        $this->assertNull(Revision::query()->find($revision->id));
    }

    /**
     * La cotizacion 7 tiene recursos sin asignar bien.
     *
     * Deja constancia del caso que motivo esta pantalla: quince recursos
     * cargados, unidades que el calculo no reconoce y un total de cero.
     */
    public function test_la_cotizacion_7_tiene_unidades_no_calculables(): void
    {
        $noCalculables = Revision::query()
            ->where('id_cotizacion', 7)
            ->whereNotNull('id_personal')
            ->whereNotIn('unidad', ['Dia Habil', 'Dia Calendario', 'Mes'])
            ->count();

        $this->assertGreaterThan(
            0,
            $noCalculables,
            'La cotizacion 7 deberia conservar las asignaciones con unidad invalida.'
        );
    }
}
