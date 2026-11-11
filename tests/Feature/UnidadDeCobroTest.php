<?php

namespace Tests\Feature;

use App\Dominio\Aiu\UnidadDeCobro;
use App\Models\Personal;
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
        foreach (UnidadDeCobro::NO_CALCULABLES as $texto) {
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
     * Solo tres tipos de recurso cobran por tiempo.
     *
     * Para vehiculos, servicios, viaticos, otros y consumibles la unidad no
     * interviene en el calculo: "N/A" ahi es correcto y no indica ningun
     * problema. Confundir ambos casos lleva a buscar errores donde no los hay.
     */
    public function test_solo_tres_recursos_cobran_por_unidad_de_tiempo(): void
    {
        $this->assertSame(
            ['personal', 'herramienta', 'equipo'],
            UnidadDeCobro::RECURSOS_POR_TIEMPO
        );

        /** En los recursos por cantidad, "N/A" es lo habitual y esta bien. */
        $porCantidad = Revision::query()
            ->whereNotNull('id_viatico')
            ->where('unidad', 'N/A')
            ->count();

        $this->assertGreaterThan(
            0,
            $porCantidad,
            'Se esperaba que los viaticos usaran N/A: cobran por cantidad, no por tiempo.'
        );
    }

    /**
     * Deja constancia del alcance exacto del fallo en los datos reales.
     *
     * Solo cuenta los recursos que SI cobran por tiempo. Si el numero crece,
     * es que se siguen guardando unidades con las que no se puede calcular.
     */
    public function test_el_alcance_de_las_unidades_no_calculables_no_crecio(): void
    {
        $sensibles = [
            'id_personal' => ['Dia Habil', 'Dia Calendario', 'Mes'],
            'id_herramienta' => ['Dia Habil', 'Mes'],
            'id_equipo' => ['Dia Habil', 'Mes'],
        ];

        $afectadas = [];
        $total = 0;

        foreach ($sensibles as $columna => $validas) {
            $malas = Revision::query()
                ->whereNotNull($columna)
                ->whereNotIn('unidad', $validas)
                ->get(['id_cotizacion']);

            $total += $malas->count();

            foreach ($malas as $revision) {
                $afectadas[$revision->id_cotizacion] = true;
            }
        }

        $this->assertSame(
            13,
            $total,
            'Cambio el numero de recursos por tiempo con unidad no calculable.'
        );

        $cotizaciones = array_keys($afectadas);
        sort($cotizaciones);

        $this->assertSame(
            [6, 7],
            $cotizaciones,
            'Aparecieron cotizaciones nuevas afectadas por el fallo de unidades.'
        );
    }

    /**
     * Las dos cotizaciones afectadas son datos de prueba, no trabajo real.
     *
     * Importa dejarlo asentado: el fallo existe y hay que corregirlo, pero
     * ninguna cotizacion real llego al cliente con un precio incompleto. Se
     * reconocen porque traen nombres de relleno en latin y salarios de dos
     * cifras.
     */
    public function test_las_cotizaciones_afectadas_son_datos_de_prueba(): void
    {
        $salariosIrrisorios = Personal::query()
            ->whereIn('id_cotizacion', [6, 7])
            ->where('salario', '<', 1000)
            ->count();

        $this->assertGreaterThan(
            0,
            $salariosIrrisorios,
            'Las cotizaciones 6 y 7 deberian contener registros de prueba (salarios de dos cifras).'
        );
    }

    /**
     * La cotizacion 7 es el caso de referencia: recursos cargados, total cero.
     *
     * Sirve para comprobar, cuando el motor de costos nuevo este completo,
     * que deja de dar cero.
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
