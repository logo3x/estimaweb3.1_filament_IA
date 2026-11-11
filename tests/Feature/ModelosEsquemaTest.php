<?php

namespace Tests\Feature;

use App\Models\Aiu;
use App\Models\Cotizacion;
use App\Models\Herramienta;
use App\Models\ItemCotizacion;
use App\Models\Personal;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifica que los modelos migrados lean correctamente los datos reales.
 *
 * Estas pruebas corren sobre la base de pruebas, que se recrea desde las
 * migraciones y se llena con una copia de los datos de produccion. Si un
 * modelo apunta a una tabla o columna que no existe, o una relacion quedo
 * invertida, aqui falla.
 */
class ModelosEsquemaTest extends TestCase
{
    /** Cada modelo apunta a una tabla que existe de verdad. */
    public function test_todas_las_tablas_de_los_modelos_existen(): void
    {
        $modelos = [
            \App\Models\Aiu::class,
            \App\Models\Aseo::class,
            \App\Models\Comunicacion::class,
            \App\Models\Consumible::class,
            \App\Models\Cotizacion::class,
            \App\Models\DatosComunicacion::class,
            \App\Models\DatosConsumible::class,
            \App\Models\DatosEquipo::class,
            \App\Models\DatosHerramienta::class,
            \App\Models\Epp::class,
            \App\Models\Equipo::class,
            \App\Models\ExamenVacuna::class,
            \App\Models\Gerencia::class,
            \App\Models\Herramienta::class,
            \App\Models\ImpresionRegistro::class,
            \App\Models\ItemConsumible::class,
            \App\Models\ItemCotizacion::class,
            \App\Models\ItemEquipo::class,
            \App\Models\ItemHerramienta::class,
            \App\Models\Oficina::class,
            \App\Models\Otro::class,
            \App\Models\PerAuxOtro::class,
            \App\Models\Personal::class,
            \App\Models\PersonalEpp::class,
            \App\Models\PersonalExamenVacuna::class,
            \App\Models\Revision::class,
            \App\Models\Servicio::class,
            \App\Models\Turno::class,
            \App\Models\User::class,
            \App\Models\Vehiculo::class,
            \App\Models\Viatico::class,
        ];

        foreach ($modelos as $modelo) {
            $tabla = (new $modelo)->getTable();

            $this->assertTrue(
                Schema::hasTable($tabla),
                "El modelo {$modelo} apunta a la tabla '{$tabla}', que no existe."
            );
        }
    }

    /** Cada columna declarada en fillable existe en su tabla. */
    public function test_los_campos_fillable_existen_en_la_tabla(): void
    {
        $modelos = [
            \App\Models\Aiu::class,
            \App\Models\Cotizacion::class,
            \App\Models\Herramienta::class,
            \App\Models\ItemCotizacion::class,
            \App\Models\Personal::class,
            \App\Models\PersonalEpp::class,
            \App\Models\Revision::class,
            \App\Models\Vehiculo::class,
        ];

        foreach ($modelos as $modelo) {
            $instancia = new $modelo;
            $tabla = $instancia->getTable();
            $columnas = Schema::getColumnListing($tabla);

            foreach ($instancia->getFillable() as $campo) {
                $this->assertContains(
                    $campo,
                    $columnas,
                    "{$modelo} declara '{$campo}' en fillable, pero '{$tabla}' no tiene esa columna."
                );
            }
        }
    }

    /** Las relaciones del nucleo devuelven los datos esperados. */
    public function test_las_relaciones_de_una_cotizacion_cargan(): void
    {
        $cotizacion = Cotizacion::query()
            ->whereHas('items')
            ->whereHas('aiu')
            ->first();

        $this->assertNotNull($cotizacion, 'No hay datos cargados para la prueba.');

        $this->assertInstanceOf(Aiu::class, $cotizacion->aiu);
        $this->assertTrue($cotizacion->items->isNotEmpty());
        $this->assertInstanceOf(ItemCotizacion::class, $cotizacion->items->first());

        /** La vuelta: desde el AIU se llega a la misma cotizacion. */
        $this->assertSame($cotizacion->id, $cotizacion->aiu->cotizacion->id);
    }

    /**
     * Una revision apunta a un solo recurso y lo resuelve sin que el codigo
     * tenga que saber cual de las once columnas vino llena.
     */
    public function test_una_revision_resuelve_su_recurso(): void
    {
        $revision = Revision::query()->whereNotNull('id_personal')->first();

        $this->assertNotNull($revision, 'No hay revisiones de personal cargadas.');

        $this->assertSame('personal', $revision->tipoDeRecurso());
        $this->assertInstanceOf(Personal::class, $revision->recurso());
        $this->assertSame($revision->id_personal, $revision->recurso()->id);
    }

    /**
     * Ninguna revision apunta a dos recursos a la vez.
     *
     * Todo el calculo asume que cada fila tiene un unico recurso; si esto
     * falla, los totales se duplican en silencio.
     */
    public function test_ninguna_revision_apunta_a_dos_recursos(): void
    {
        $columnas = array_keys(Revision::RECURSOS);
        $suma = implode(' + ', array_map(fn (string $c): string => "({$c} IS NOT NULL)", $columnas));

        $conflictivas = DB::table('revisions')
            ->whereRaw("({$suma}) > 1")
            ->count();

        $this->assertSame(0, $conflictivas, "Hay {$conflictivas} revisiones con mas de un recurso asignado.");
    }

    /** asignarRecurso deja una sola columna llena, sin importar el estado previo. */
    public function test_asignar_recurso_limpia_las_demas_columnas(): void
    {
        $personal = Personal::query()->first();
        $herramienta = Herramienta::query()->first();

        $this->assertNotNull($personal);
        $this->assertNotNull($herramienta);

        $revision = new Revision;
        $revision->asignarRecurso($personal);
        $this->assertSame($personal->id, $revision->id_personal);

        /** Reasignar a otro tipo debe borrar el anterior, no acumularlo. */
        $revision->asignarRecurso($herramienta);

        $this->assertNull($revision->id_personal);
        $this->assertSame($herramienta->id, $revision->id_herramienta);
        $this->assertSame('herramienta', $revision->tipoDeRecurso());
    }

    /** El factor del AIU convierte los tres porcentajes en un multiplicador. */
    public function test_el_factor_del_aiu_se_calcula_sobre_los_tres_porcentajes(): void
    {
        $aiu = new Aiu([
            'administracion' => 10.0,
            'imprevistos' => 5.0,
            'utilidad' => 8.0,
        ]);

        $this->assertEqualsWithDelta(1.23, $aiu->factor(), 0.0001);

        $sinAiu = new Aiu([
            'administracion' => 0.0,
            'imprevistos' => 0.0,
            'utilidad' => 0.0,
        ]);

        $this->assertEqualsWithDelta(1.0, $sinAiu->factor(), 0.0001);
    }

    /** Las banderas de texto SI/NO se leen por metodo, no comparando cadenas. */
    public function test_las_banderas_del_aiu_se_leen_como_booleanos(): void
    {
        $obra = new Aiu(['contrato_obra' => 'SI', 'mostrar_aiu' => 'NO']);

        $this->assertTrue($obra->esContratoDeObra());
        $this->assertFalse($obra->muestraAiuDesglosado());

        $servicio = new Aiu(['contrato_obra' => 'NO', 'mostrar_aiu' => 'SI']);

        $this->assertFalse($servicio->esContratoDeObra());
        $this->assertTrue($servicio->muestraAiuDesglosado());
    }

    /** El autor de una cotizacion se resuelve con belongsTo, no al reves. */
    public function test_la_cotizacion_resuelve_su_autor(): void
    {
        $cotizacion = Cotizacion::query()->whereNotNull('id_user')->first();

        $this->assertNotNull($cotizacion, 'No hay cotizaciones con autor cargadas.');
        $this->assertInstanceOf(User::class, $cotizacion->autor);
        $this->assertSame($cotizacion->id_user, $cotizacion->autor->id);
    }
}
