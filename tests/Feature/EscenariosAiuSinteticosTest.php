<?php

namespace Tests\Feature;

use App\Dominio\Aiu\CalculadoraAiu;
use App\Dominio\Aiu\ParametrosAiu;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cubre las combinaciones del AIU que los datos reales no alcanzan.
 *
 * Las 48 cotizaciones de produccion solo ejercitan tres de los cuatro
 * escenarios: ninguna usa obra con el AIU oculto. Un error en esa rama no
 * haria fallar ningun golden master, asi que se cubre con cifras redondas
 * calculadas a mano.
 *
 * Se comprobo que esta prueba sirve: al alterar la regla del IVA de obra,
 * falla aqui y no en los golden masters.
 */
class EscenariosAiuSinteticosTest extends TestCase
{
    private const MARGEN = 0.01;

    private CalculadoraAiu $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = new CalculadoraAiu;
    }

    /**
     * Los cuatro escenarios sobre un millon de costo directo, con
     * A=10%, I=5%, U=8% (factor 1,23).
     *
     * Cifras calculadas a mano:
     *   costo directo            1.000.000
     *   administracion (10%)       100.000
     *   imprevistos     (5%)        50.000
     *   utilidad        (8%)        80.000
     *   subtotal                 1.230.000
     *
     *   IVA en obra    : 19% de la utilidad  =    15.200
     *   IVA fuera obra : 19% del subtotal    =   233.700
     *
     * @return array<string, array{bool, bool, float, float}>
     */
    public static function escenarios(): array
    {
        return [
            'obra / aiu visible' => [true, true, 15_200.0, 1_245_200.0],
            'obra / aiu oculto' => [true, false, 15_200.0, 1_245_200.0],
            'servicio / aiu visible' => [false, true, 233_700.0, 1_463_700.0],
            'servicio / aiu oculto' => [false, false, 233_700.0, 1_463_700.0],
        ];
    }

    /**
     * Cada escenario da el IVA y el total esperados.
     *
     * Lo importante: mostrar u ocultar el AIU NO cambia una sola cifra. Solo
     * cambia como se presenta en el documento. El sistema actual mezclaba
     * presentacion con calculo y por eso el mismo contrato daba numeros
     * distintos segun la pantalla.
     */
    #[DataProvider('escenarios')]
    public function test_cada_escenario_da_el_iva_y_el_total_esperados(
        bool $esObra,
        bool $muestraAiu,
        float $ivaEsperado,
        float $totalEsperado,
    ): void {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, $esObra, $muestraAiu);

        $resultado = $this->calculadora->calcular(1_000_000.0, $parametros);

        $this->assertEqualsWithDelta(100_000.0, $resultado->administracion, self::MARGEN);
        $this->assertEqualsWithDelta(50_000.0, $resultado->imprevistos, self::MARGEN);
        $this->assertEqualsWithDelta(80_000.0, $resultado->utilidad, self::MARGEN);
        $this->assertEqualsWithDelta(1_230_000.0, $resultado->subtotal, self::MARGEN);

        $this->assertEqualsWithDelta(
            $ivaEsperado,
            $resultado->iva,
            self::MARGEN,
            "Escenario {$parametros->escenario()}: IVA incorrecto."
        );

        $this->assertEqualsWithDelta(
            $totalEsperado,
            $resultado->total,
            self::MARGEN,
            "Escenario {$parametros->escenario()}: total incorrecto."
        );
    }

    /**
     * Obra con el AIU oculto: el escenario que ningun dato real cubre.
     *
     * Partiendo del costo que ya incorpora el AIU, el desglose despejado debe
     * reconstruir exactamente el costo directo original.
     */
    public function test_obra_con_aiu_oculto_despeja_bien_el_costo_directo(): void
    {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, esContratoDeObra: true, muestraAiuDesglosado: false);

        /** 1.000.000 de costo directo ya multiplicado por el factor 1,23. */
        $resultado = $this->calculadora->calcularDesdeCostoConAiu(1_230_000.0, $parametros);

        $this->assertEqualsWithDelta(1_000_000.0, $resultado->costoDirecto, self::MARGEN);
        $this->assertEqualsWithDelta(1_230_000.0, $resultado->subtotal, self::MARGEN);

        /** El AIU desglosado suma exactamente lo que el subtotal contiene. */
        $this->assertEqualsWithDelta(230_000.0, $resultado->aiuEnPesos(), self::MARGEN);

        /** En obra, el IVA sigue gravando solo la utilidad. */
        $this->assertEqualsWithDelta(80_000.0 * 0.19, $resultado->iva, self::MARGEN);
    }

    /**
     * Despejar y volver a aplicar el factor devuelve el punto de partida.
     *
     * Es la garantia de que los dos caminos de calculo son coherentes entre
     * si, que es justo lo que fallaba en el sistema actual.
     */
    #[DataProvider('escenarios')]
    public function test_despejar_y_reaplicar_el_factor_es_reversible(
        bool $esObra,
        bool $muestraAiu,
    ): void {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, $esObra, $muestraAiu);

        $costoConAiu = 1_000_000.0 * $parametros->factor();

        $resultado = $this->calculadora->calcularDesdeCostoConAiu($costoConAiu, $parametros);

        $this->assertEqualsWithDelta(1_000_000.0, $resultado->costoDirecto, self::MARGEN);

        $this->assertEqualsWithDelta(
            $resultado->subtotal,
            $resultado->costoDirecto + $resultado->aiuEnPesos(),
            self::MARGEN,
            'El desglose no reconstruye el subtotal.'
        );
    }

    /** Con el AIU en cero, el costo pasa intacto al subtotal. */
    public function test_con_aiu_en_cero_el_subtotal_es_el_costo_directo(): void
    {
        $parametros = new ParametrosAiu(0.0, 0.0, 0.0, false, false);

        $this->assertEqualsWithDelta(1.0, $parametros->factor(), self::MARGEN);

        $directo = $this->calculadora->calcular(1_000_000.0, $parametros);
        $despejado = $this->calculadora->calcularDesdeCostoConAiu(1_000_000.0, $parametros);

        $this->assertEqualsWithDelta(1_000_000.0, $directo->subtotal, self::MARGEN);
        $this->assertEqualsWithDelta(1_000_000.0, $despejado->subtotal, self::MARGEN);
        $this->assertEqualsWithDelta($directo->total, $despejado->total, self::MARGEN);
    }

    /** Un costo de cero no rompe nada ni produce divisiones invalidas. */
    public function test_un_costo_de_cero_no_rompe_el_calculo(): void
    {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, false, false);

        $resultado = $this->calculadora->calcular(0.0, $parametros);

        $this->assertSame(0.0, $resultado->subtotal);
        $this->assertSame(0.0, $resultado->total);
        $this->assertSame(0.0, $resultado->aiuEnPesos());
    }

    /**
     * En obra, el IVA jamas grava el costo directo ni la administracion.
     *
     * Es la diferencia de fondo entre los dos tipos de contrato y la que mas
     * dinero mueve: sobre un millon, 15.200 en vez de 233.700.
     */
    public function test_en_obra_el_iva_nunca_grava_el_costo_directo(): void
    {
        foreach ([true, false] as $muestraAiu) {
            $parametros = new ParametrosAiu(10.0, 5.0, 8.0, esContratoDeObra: true, muestraAiuDesglosado: $muestraAiu);

            $resultado = $this->calculadora->calcular(1_000_000.0, $parametros);

            $this->assertEqualsWithDelta(
                $resultado->utilidad * 0.19,
                $resultado->iva,
                self::MARGEN,
                'En obra el IVA debe salir solo de la utilidad.'
            );

            $this->assertLessThan(
                $resultado->subtotal * 0.19,
                $resultado->iva,
                'El IVA de obra no puede igualar al de un contrato normal.'
            );
        }
    }
}
