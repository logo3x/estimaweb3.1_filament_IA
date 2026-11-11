<?php

namespace Tests\Feature;

use App\Dominio\Recursos\CalculadoraAmortizacion;
use App\Dominio\Recursos\InteresConstante;
use App\Models\Equipo;
use App\Models\Herramienta;
use Tests\TestCase;

/**
 * Amortizacion de herramientas y equipos.
 *
 * A diferencia del resto del dominio, aqui los numeros SI cambian respecto al
 * sistema actual, y a proposito: alli el interes constante nunca llego a
 * aplicarse porque la tabla se busca con claves como "12" mientras MySQL
 * devuelve "12.00". La formula estaba escrita pero no funcionaba.
 *
 * Las pruebas comprueban que la diferencia es exactamente el interes que
 * faltaba, ni mas ni menos.
 */
class CalculadoraAmortizacionTest extends TestCase
{
    private const MARGEN = 0.01;

    private CalculadoraAmortizacion $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = new CalculadoraAmortizacion;
    }

    /**
     * EL FALLO CORREGIDO: el periodo se encuentra venga como venga.
     *
     * MySQL devuelve decimal(4,2) como la cadena "12.00". El sistema actual
     * busca esa clave literal en una tabla que tiene "12", no la encuentra y
     * devuelve cero.
     */
    public function test_encuentra_el_periodo_en_cualquier_formato(): void
    {
        $esperado = 0.069227858;

        foreach (['12', '12.0', '12.00', 12, 12.0] as $formato) {
            $this->assertEqualsWithDelta(
                $esperado,
                InteresConstante::para($formato),
                0.0000001,
                'El periodo ' . var_export($formato, true) . ' deberia encontrarse en la tabla.'
            );
        }
    }

    /** Los periodos de medio ano tambien se normalizan bien. */
    public function test_normaliza_los_periodos_de_medio_ano(): void
    {
        $this->assertEqualsWithDelta(-0.123611063, InteresConstante::para('1.50'), 0.0000001);
        $this->assertEqualsWithDelta(-0.123611063, InteresConstante::para(1.5), 0.0000001);
    }

    /** Un periodo fuera de la tabla no rompe el calculo. */
    public function test_un_periodo_desconocido_devuelve_cero(): void
    {
        $this->assertSame(0.0, InteresConstante::para('99'));
        $this->assertSame(0.0, InteresConstante::para(null));

        $this->assertFalse(InteresConstante::conoce('99'));
        $this->assertTrue(InteresConstante::conoce('12.00'));
    }

    /**
     * Los periodos realmente en uso estan todos en la tabla.
     *
     * Si aparece uno nuevo que no este, su interes seria cero sin aviso.
     */
    public function test_los_periodos_en_uso_estan_cubiertos(): void
    {
        $periodos = Herramienta::query()
            ->distinct()
            ->pluck('periodo_evaluacion')
            ->merge(Equipo::query()->distinct()->pluck('periodo_evaluacion'))
            ->unique();

        foreach ($periodos as $periodo) {
            if ((float) $periodo <= 0) {
                continue;
            }

            $this->assertTrue(
                InteresConstante::conoce($periodo),
                "El periodo {$periodo} no esta en la tabla de interes: su amortizacion saldria mal."
            );
        }
    }

    /**
     * La diferencia con el sistema actual es exactamente el interes.
     *
     * Se calcula el costo con la formula nueva y con el interes forzado a
     * cero (que es lo que hace el sistema actual) y se comprueba que la
     * diferencia es la que predice la tabla.
     */
    public function test_la_diferencia_con_el_sistema_actual_es_el_interes(): void
    {
        $valorCompra = 10_000_000.0;
        $periodo = '12.00';
        $interes = InteresConstante::para($periodo);

        $conInteres = $this->calculadora->porMes($valorCompra, $periodo, 0.0, 0.0);

        /**
         * Lo que calcula hoy el sistema: la misma formula y el mismo periodo,
         * pero con el interes en cero porque nunca lo encuentra en la tabla.
         *
         * Se reproduce aqui a mano y no llamando a la calculadora con otro
         * periodo: cambiar el periodo cambiaria tambien el divisor y se
         * estarian comparando dos cosas distintas.
         */
        $sinInteres = $valorCompra / (12 * 12) * (1 + 0.1) ** (1 / 12);

        $this->assertEqualsWithDelta(
            $sinInteres * (1 + $interes),
            $conInteres,
            self::MARGEN,
            'La diferencia deberia ser exactamente el factor de interes.'
        );

        /** A 12 anos el interes encarece el costo un 6,92 por ciento. */
        $this->assertGreaterThan($sinInteres, $conInteres);
    }

    /**
     * Un periodo corto abarata el costo mensual y uno largo lo encarece.
     *
     * Es la logica financiera de la tabla: a dos anos el interes es negativo
     * (-16,4 por ciento) y a veinticuatro es muy positivo (+43,7).
     */
    public function test_el_periodo_corto_abarata_y_el_largo_encarece(): void
    {
        $this->assertLessThan(0, InteresConstante::para('2'));
        $this->assertGreaterThan(0, InteresConstante::para('24'));
        $this->assertGreaterThan(InteresConstante::para('12'), InteresConstante::para('24'));
    }

    /** La reposicion y la calibracion se suman al costo mensual. */
    public function test_suma_la_reposicion_y_la_calibracion(): void
    {
        $valorCompra = 12_000_000.0;

        $base = $this->calculadora->porMes($valorCompra, '12.00', 0.0, 0.0);
        $conReposicion = $this->calculadora->porMes($valorCompra, '12.00', 10.0, 0.0);

        /** 10 por ciento anual de 12 millones, repartido en doce meses. */
        $this->assertEqualsWithDelta(
            100_000.0,
            $conReposicion - $base,
            self::MARGEN
        );

        $conAmbas = $this->calculadora->porMes($valorCompra, '12.00', 10.0, 5.0);

        $this->assertEqualsWithDelta(150_000.0, $conAmbas - $base, self::MARGEN);
    }

    /** El dia habil reparte el mes entre los dias que se trabajan. */
    public function test_el_dia_habil_sale_del_costo_mensual(): void
    {
        $mes = $this->calculadora->porMes(10_000_000.0, '12.00', 5.0, 2.0);
        $dia = $this->calculadora->porDiaHabil(10_000_000.0, '12.00', 5.0, 2.0);

        /** (365 - 52 - 52 - 18) / 12 = 20,25 dias habiles al mes. */
        $this->assertEqualsWithDelta($mes / 20.25, $dia, self::MARGEN);
        $this->assertGreaterThan($dia, $mes);
    }

    /** Sin valor de compra o sin periodo, no hay nada que amortizar. */
    public function test_sin_datos_no_hay_costo(): void
    {
        $this->assertSame(0.0, $this->calculadora->porMes(0.0, '12.00', 5.0, 2.0));
        $this->assertSame(0.0, $this->calculadora->porMes(1_000_000.0, '0', 5.0, 2.0));
        $this->assertSame(0.0, $this->calculadora->porMes(1_000_000.0, null, 5.0, 2.0));
    }

    /**
     * Deja constancia del impacto sobre los activos reales.
     *
     * Si cambia el numero, es que se cargaron herramientas o equipos con
     * periodos distintos y conviene revisar el efecto.
     */
    public function test_el_alcance_sobre_los_activos_reales(): void
    {
        $afectados = 0;

        foreach (Herramienta::query()->get() as $herramienta) {
            if (InteresConstante::para($herramienta->periodo_evaluacion) !== 0.0) {
                $afectados++;
            }
        }

        foreach (Equipo::query()->get() as $equipo) {
            if (InteresConstante::para($equipo->periodo_evaluacion) !== 0.0) {
                $afectados++;
            }
        }

        $this->assertSame(
            34,
            $afectados,
            'Cambio el numero de herramientas y equipos cuyo costo corrige el interes.'
        );
    }
}
