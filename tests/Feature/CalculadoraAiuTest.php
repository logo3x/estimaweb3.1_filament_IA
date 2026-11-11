<?php

namespace Tests\Feature;

use App\Dominio\Aiu\CalculadoraAiu;
use App\Dominio\Aiu\ParametrosAiu;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La formula unica del AIU, contra los numeros congelados del sistema actual.
 *
 * La prueba central es la ultima: toma el costo directo de cada cotizacion
 * real y comprueba que la calculadora nueva llega a los mismos totales que
 * el sistema en produccion. Si alguna difiere, aparece nombrada.
 */
class CalculadoraAiuTest extends TestCase
{
    /** Margen en pesos: absorbe redondeo, no una diferencia de formula. */
    private const MARGEN = 0.02;

    private CalculadoraAiu $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = new CalculadoraAiu;
    }

    /** El factor convierte los tres porcentajes en un multiplicador. */
    public function test_el_factor_incorpora_los_tres_porcentajes(): void
    {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, false, false);

        $this->assertEqualsWithDelta(1.23, $parametros->factor(), 0.0001);
        $this->assertEqualsWithDelta(23.0, $parametros->porcentajeTotal(), 0.0001);
    }

    /** Sin AIU, el costo directo no se incrementa. */
    public function test_sin_aiu_el_costo_directo_no_cambia(): void
    {
        $resultado = $this->calculadora->calcular(1_000_000.0, ParametrosAiu::sinAiu());

        $this->assertEqualsWithDelta(1_000_000.0, $resultado->subtotal, self::MARGEN);
        $this->assertEqualsWithDelta(0.0, $resultado->aiuEnPesos(), self::MARGEN);

        /** Sin AIU sigue siendo un contrato gravado al 19%. */
        $this->assertEqualsWithDelta(190_000.0, $resultado->iva, self::MARGEN);
    }

    /**
     * En contrato de obra el IVA grava solo la utilidad.
     *
     * Es la diferencia de fondo entre los dos tipos de contrato, y la que mas
     * dinero mueve.
     */
    public function test_en_contrato_de_obra_el_iva_grava_solo_la_utilidad(): void
    {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, esContratoDeObra: true, muestraAiuDesglosado: true);

        $resultado = $this->calculadora->calcular(1_000_000.0, $parametros);

        $this->assertEqualsWithDelta(80_000.0, $resultado->utilidad, self::MARGEN);
        $this->assertEqualsWithDelta(80_000.0 * 0.19, $resultado->iva, self::MARGEN);
        $this->assertEqualsWithDelta(1_230_000.0 + 15_200.0, $resultado->total, self::MARGEN);
    }

    /** Si no es obra, el IVA grava el subtotal completo. */
    public function test_fuera_de_obra_el_iva_grava_el_subtotal_completo(): void
    {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, esContratoDeObra: false, muestraAiuDesglosado: true);

        $resultado = $this->calculadora->calcular(1_000_000.0, $parametros);

        $this->assertEqualsWithDelta(1_230_000.0, $resultado->subtotal, self::MARGEN);
        $this->assertEqualsWithDelta(1_230_000.0 * 0.19, $resultado->iva, self::MARGEN);
    }

    /**
     * Mostrar u ocultar el AIU no cambia cuanto se paga.
     *
     * Solo cambia como se presenta. El sistema actual mezclaba presentacion
     * con calculo, y por eso un mismo contrato daba cifras distintas segun la
     * pantalla desde la que se mirara.
     */
    public function test_mostrar_el_aiu_no_altera_el_total(): void
    {
        $visible = new ParametrosAiu(10.0, 5.0, 8.0, false, muestraAiuDesglosado: true);
        $oculto = new ParametrosAiu(10.0, 5.0, 8.0, false, muestraAiuDesglosado: false);

        $conVisible = $this->calculadora->calcular(1_000_000.0, $visible);
        $conOculto = $this->calculadora->calcular(1_000_000.0, $oculto);

        $this->assertEqualsWithDelta($conVisible->total, $conOculto->total, self::MARGEN);
        $this->assertEqualsWithDelta($conVisible->subtotal, $conOculto->subtotal, self::MARGEN);
    }

    /**
     * El valor unitario nunca lleva IVA.
     *
     * Con el IVA dentro, la suma de los items superaba la fila SUBTOTAL
     * porque el IVA se contaba dos veces. Fue el error reportado sobre la
     * cotizacion 55.
     */
    public function test_el_valor_unitario_no_incluye_iva(): void
    {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, false, muestraAiuDesglosado: false);

        $unitario = $this->calculadora->valorUnitarioDeItem(1_000_000.0, 1, $parametros);

        /** Costo mas AIU (1.23), sin el 19% encima. */
        $this->assertEqualsWithDelta(1_230_000.0, $unitario, self::MARGEN);
        $this->assertNotEqualsWithDelta(1_230_000.0 * 1.19, $unitario, 1.0);
    }

    /** Con el AIU visible, el item muestra su costo directo. */
    public function test_con_aiu_visible_el_item_muestra_el_costo_directo(): void
    {
        $parametros = new ParametrosAiu(10.0, 5.0, 8.0, false, muestraAiuDesglosado: true);

        $unitario = $this->calculadora->valorUnitarioDeItem(1_000_000.0, 1, $parametros);

        $this->assertEqualsWithDelta(1_000_000.0, $unitario, self::MARGEN);
    }

    /** El costo se reparte entre las unidades del item. */
    public function test_el_valor_unitario_reparte_entre_la_cantidad(): void
    {
        $parametros = ParametrosAiu::sinAiu();

        $this->assertEqualsWithDelta(
            250_000.0,
            $this->calculadora->valorUnitarioDeItem(1_000_000.0, 4, $parametros),
            self::MARGEN
        );

        /** Cantidad invalida: devuelve cero en vez de dividir por cero. */
        $this->assertSame(0.0, $this->calculadora->valorUnitarioDeItem(1_000_000.0, 0, $parametros));
    }

    /** Una duracion sin definir no revienta el calculo. */
    public function test_prorratear_tolera_una_duracion_sin_definir(): void
    {
        $this->assertSame(0.0, $this->calculadora->prorratear(100_000.0, 0.0));
        $this->assertSame(0.0, $this->calculadora->prorratear(100_000.0, null));
        $this->assertSame(0.0, $this->calculadora->prorratear(100_000.0, -5.0));

        $this->assertEqualsWithDelta(1_000.0, $this->calculadora->prorratear(30_000.0, 30.0), self::MARGEN);
    }

    /** Un porcentaje negativo es un dato invalido, no un descuento. */
    public function test_rechaza_porcentajes_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParametrosAiu(-5.0, 0.0, 0.0, false, false);
    }

    /** Los cuatro escenarios tienen nombre propio. */
    public function test_nombra_los_cuatro_escenarios(): void
    {
        $this->assertSame('obra/aiu-visible', (new ParametrosAiu(1, 1, 1, true, true))->escenario());
        $this->assertSame('obra/aiu-oculto', (new ParametrosAiu(1, 1, 1, true, false))->escenario());
        $this->assertSame('servicio/aiu-visible', (new ParametrosAiu(1, 1, 1, false, true))->escenario());
        $this->assertSame('servicio/aiu-oculto', (new ParametrosAiu(1, 1, 1, false, false))->escenario());
    }

    /**
     * @return array<string, array{int, string}>
     *
     * Ruta desde __DIR__: los data providers corren antes de que la
     * aplicacion arranque, asi que base_path() no existe todavia.
     */
    public static function cotizacionesCongeladas(): array
    {
        $casos = [];

        foreach (glob(__DIR__ . '/../golden/cotizacion-*.json') ?: [] as $archivo) {
            $datos = json_decode((string) file_get_contents($archivo), true);

            if (! is_array($datos)) {
                continue;
            }

            $casos["cotizacion {$datos['cotizacion_id']} ({$datos['escenario']})"] = [
                (int) $datos['cotizacion_id'],
                $archivo,
            ];
        }

        return $casos;
    }

    /**
     * LA PRUEBA QUE IMPORTA: la formula nueva reproduce los numeros del
     * sistema actual sobre cada cotizacion real.
     *
     * Parte del costo directo que congelo el sistema actual y comprueba que,
     * aplicando la formula nueva, se llega al mismo subtotal, al mismo IVA y
     * al mismo total. Es la verificacion de que nada cambia de precio al
     * migrar.
     */
    #[DataProvider('cotizacionesCongeladas')]
    public function test_reproduce_los_totales_del_sistema_actual(int $id, string $archivo): void
    {
        $esperado = json_decode((string) file_get_contents($archivo), true);

        $parametros = new ParametrosAiu(
            administracion: (float) $esperado['parametros_aiu']['administracion'],
            imprevistos: (float) $esperado['parametros_aiu']['imprevistos'],
            utilidad: (float) $esperado['parametros_aiu']['utilidad'],
            esContratoDeObra: $esperado['parametros_aiu']['contrato_obra'] === 'SI',
            muestraAiuDesglosado: $esperado['parametros_aiu']['mostrar_aiu'] === 'SI',
        );

        /**
         * La columna valor_total del sistema actual NO significa lo mismo en
         * los dos escenarios, y confundirlos es el error que este golden
         * master detecto:
         *
         * - AIU oculto : el factor ya se aplico item por item, asi que el
         *   acumulado llega CON el AIU dentro. Aplicarselo otra vez lo cobra
         *   dos veces (en la cotizacion 55, 815 millones en vez de 713).
         * - AIU visible: los items guardan su costo directo y A+I+U se suman
         *   aparte, asi que hay que aplicarlo.
         */
        $base = (float) $esperado['totales']['sin_aiu'];

        if ($base <= 0.0) {
            $this->markTestSkipped("Cotizacion {$id}: sin costo directo (borrador sin recursos).");
        }

        $resultado = $parametros->seAplicaPorItem()
            ? $this->calculadora->calcularDesdeCostoConAiu($base, $parametros)
            : $this->calculadora->calcular($base, $parametros);

        /** Margen proporcional: el redondeo acumula sobre cifras grandes. */
        $margen = max(self::MARGEN, $base * 0.0000001);

        /**
         * EL TOTAL FACTURADO NO CAMBIA. Es lo que se le cobra al cliente y lo
         * que debe coincidir en las 48 cotizaciones.
         */
        $this->assertEqualsWithDelta(
            (float) $esperado['totales']['con_iva'],
            $resultado->total,
            $margen,
            "Cotizacion {$id} ({$parametros->escenario()}): difiere el total facturado."
        );

        $this->assertEqualsWithDelta(
            (float) $esperado['totales']['con_aiu'],
            $resultado->subtotal,
            $margen,
            "Cotizacion {$id} ({$parametros->escenario()}): difiere el subtotal."
        );

        /**
         * El desglose A+I+U si cambia a proposito con el AIU oculto.
         *
         * El sistema actual saca los porcentajes sobre un valor que ya
         * incorpora el AIU, es decir, aplica un porcentaje sobre si mismo, y
         * el desglose sale inflado (en la cotizacion 55, 10,7 millones de mas
         * sobre 33 cotizaciones afectadas). No altera lo que se cobra ni se
         * imprime en el documento, porque con el AIU oculto ese desglose no
         * se muestra, pero como dato interno esta mal.
         *
         * Decision tomada: la version nueva despeja el costo directo antes de
         * calcular los porcentajes, de modo que A+I+U sume exactamente lo que
         * el subtotal contiene.
         */
        if ($parametros->seAplicaPorItem()) {
            $this->assertEqualsWithDelta(
                $resultado->subtotal - $resultado->costoDirecto,
                $resultado->aiuEnPesos(),
                $margen,
                "Cotizacion {$id}: el desglose A+I+U no suma el AIU contenido en el subtotal."
            );

            return;
        }

        /** Con el AIU visible, el desglose debe coincidir con el actual. */
        foreach (['administracion', 'imprevistos', 'utilidad'] as $clave) {
            $this->assertEqualsWithDelta(
                (float) $esperado['totales'][$clave],
                $resultado->{$clave},
                $margen,
                "Cotizacion {$id} ({$parametros->escenario()}): difiere '{$clave}'."
            );
        }
    }
}
