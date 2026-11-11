<?php

namespace Tests\Feature;

use App\Dominio\Aiu\UnidadDeCobro;
use App\Models\Revision;
use Tests\TestCase;

/**
 * Una unidad desconocida ya no puede hacer que un recurso valga cero.
 *
 * En el sistema actual la unidad se compara con cadenas en tres `if` sin
 * `else`. Una unidad no prevista no entra en ninguna rama y el recurso aporta
 * cero sin que nadie se entere: la cotizacion 7 tiene 15 recursos cargados,
 * con salarios de 3,2 y 5,5 millones, y da un total de cero pesos.
 *
 * Es peor que un error visible. Un error se ve; esto solo produce un precio
 * mas bajo de lo que deberia.
 */
class UnidadDeCobroTest extends TestCase
{
    /** Las tres unidades con las que el calculo sabe trabajar. */
    public function test_reconoce_las_unidades_calculables(): void
    {
        $this->assertSame(UnidadDeCobro::DiaHabil, UnidadDeCobro::desdeTexto('Dia Habil'));
        $this->assertSame(UnidadDeCobro::DiaCalendario, UnidadDeCobro::desdeTexto('Dia Calendario'));
        $this->assertSame(UnidadDeCobro::Mes, UnidadDeCobro::desdeTexto('Mes'));
    }

    /**
     * Las unidades que hoy desaparecen en silencio se detectan.
     *
     * Devolver null obliga a quien llama a decidir que hacer. No puede
     * ignorarlas sin darse cuenta, que es lo que ocurre hoy.
     */
    public function test_detecta_las_unidades_que_hoy_se_ignoran(): void
    {
        foreach (UnidadDeCobro::NO_RECONOCIDAS as $texto) {
            $this->assertNull(
                UnidadDeCobro::desdeTexto($texto),
                "La unidad '{$texto}' deberia detectarse como no calculable."
            );

            $this->assertFalse(UnidadDeCobro::esCalculable($texto));
        }

        $this->assertNull(UnidadDeCobro::desdeTexto(null));
        $this->assertNull(UnidadDeCobro::desdeTexto('cualquier cosa'));
    }

    /** Los espacios sobrantes no convierten una unidad valida en desconocida. */
    public function test_tolera_espacios_alrededor(): void
    {
        $this->assertSame(UnidadDeCobro::Mes, UnidadDeCobro::desdeTexto('  Mes  '));
    }

    /**
     * Deja constancia del alcance exacto en los datos reales.
     *
     * Si el numero crece, es que se siguen guardando unidades que el calculo
     * no entiende.
     */
    public function test_el_alcance_de_las_unidades_no_calculables_no_crecio(): void
    {
        $revisionesDePersonal = Revision::query()
            ->whereNotNull('id_personal')
            ->get(['id', 'id_cotizacion', 'unidad']);

        $problematicas = $revisionesDePersonal
            ->reject(fn (Revision $r): bool => UnidadDeCobro::esCalculable($r->unidad));

        $this->assertCount(
            9,
            $problematicas,
            'Cambio el numero de revisiones de personal con unidad no calculable.'
        );

        $cotizaciones = $problematicas
            ->pluck('id_cotizacion')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            [6, 7],
            $cotizaciones,
            'Aparecieron cotizaciones nuevas con unidades que el calculo ignora.'
        );
    }

    /**
     * La cotizacion 7 es el caso de referencia: recursos cargados, total cero.
     *
     * Sirve para comprobar, cuando el calculo nuevo este completo, que deja de
     * dar cero.
     */
    public function test_la_cotizacion_7_tiene_recursos_pese_a_valer_cero(): void
    {
        $revisiones = Revision::query()->where('id_cotizacion', 7)->count();

        $this->assertSame(15, $revisiones, 'La cotizacion 7 deberia tener 15 revisiones cargadas.');

        $snapshot = json_decode(
            (string) file_get_contents(__DIR__ . '/../golden/cotizacion-7.json'),
            true
        );

        $this->assertSame(
            0.0,
            (float) $snapshot['totales']['con_iva'],
            'El sistema actual daba cero para la cotizacion 7; si cambio, revisar por que.'
        );
    }

    /** Las opciones del formulario solo ofrecen unidades calculables. */
    public function test_las_opciones_del_formulario_son_todas_calculables(): void
    {
        $opciones = UnidadDeCobro::opciones();

        $this->assertCount(3, $opciones);

        foreach (array_keys($opciones) as $valor) {
            $this->assertTrue(
                UnidadDeCobro::esCalculable($valor),
                "El formulario ofrece '{$valor}', que el calculo no sabe procesar."
            );
        }
    }
}
