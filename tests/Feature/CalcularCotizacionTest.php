<?php

namespace Tests\Feature;

use App\Dominio\CalcularCotizacion;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El calculo completo de una cotizacion, de punta a punta.
 *
 * Es el unico punto de entrada al precio: pantallas, documentos e informes
 * piden aqui. Esta prueba lo contrasta con los numeros congelados del sistema
 * actual sobre las cotizaciones reales.
 */
class CalcularCotizacionTest extends TestCase
{
    /**
     * Cotizaciones cuyo total cambia a proposito.
     *
     * Son las que tienen herramientas o equipos: alli se aplica el interes
     * constante que el sistema actual nunca encontro en su tabla. Decidido
     * con el usuario el 2026-11-11.
     *
     * @var list<int>
     */
    private const CON_INTERES_CORREGIDO = [24, 26, 27, 29, 44, 45, 47];

    /**
     * Cotizaciones cuya bandera del AIU esta guardada como numero.
     *
     * El sistema actual solo entiende 'SI'/'NO', asi que las lee como si
     * dijeran 'NO' y NO LES APLICA EL AIU. No es un asunto de presentacion
     * como se penso al principio: en la cotizacion 6, con un AIU del 25%,
     * son 5.013.260 pesos que nunca se cobraron.
     *
     * Se verifico sobre los datos: la columna valor_total de esa cotizacion
     * coincide exactamente con el costo directo que calcula el motor, lo que
     * confirma que el AIU nunca llego a aplicarse.
     *
     * Ambas son cotizaciones de prueba, pero el fallo esta latente para
     * cualquiera que se guarde en ese formato.
     *
     * @var list<int>
     */
    private const BANDERA_NUMERICA = [6, 7];

    /** Margen para las que difieren por la correccion del interes. */
    private const MARGEN_INTERES = 15.0;

    /**
     * Margen para las que difieren por el AIU no aplicado.
     *
     * Con un AIU del 25%, recuperarlo mueve el total justo ese 25%.
     */
    private const MARGEN_AIU_NO_APLICADO = 30.0;

    /** @return array<string, array{int}> */
    public static function cotizacionesConCosto(): array
    {
        $casos = [];

        foreach (glob(__DIR__ . '/../golden/cotizacion-*.json') ?: [] as $archivo) {
            $datos = json_decode((string) file_get_contents($archivo), true);

            if (! is_array($datos) || (float) $datos['totales']['con_iva'] <= 0) {
                continue;
            }

            $casos["cotizacion {$datos['cotizacion_id']}"] = [(int) $datos['cotizacion_id']];
        }

        return $casos;
    }

    /**
     * EL CONTRASTE FINAL: el total facturado coincide con el sistema actual.
     *
     * Las que difieren lo hacen por las dos correcciones decididas, y la
     * prueba exige que la diferencia se mantenga acotada: una correccion que
     * duplicara un precio seria un error, no una mejora.
     */
    #[DataProvider('cotizacionesConCosto')]
    public function test_el_total_coincide_con_el_sistema_actual(int $id): void
    {
        $golden = json_decode(
            (string) file_get_contents(__DIR__ . "/../golden/cotizacion-{$id}.json"),
            true
        );

        $resultado = CalcularCotizacion::crear()->para(Cotizacion::findOrFail($id));

        $esperado = (float) $golden['totales']['con_iva'];
        $obtenido = $resultado->totales->total;

        $desviacion = abs($obtenido - $esperado) / $esperado * 100;

        $margen = match (true) {
            in_array($id, self::BANDERA_NUMERICA, true) => self::MARGEN_AIU_NO_APLICADO,
            in_array($id, self::CON_INTERES_CORREGIDO, true) => self::MARGEN_INTERES,
            default => null,
        };

        if ($margen !== null) {
            $this->assertLessThan(
                $margen,
                $desviacion,
                "Cotizacion {$id}: la correccion movio el total mas de lo previsto "
                . '(' . number_format($esperado, 2) . ' -> ' . number_format($obtenido, 2) . ').'
            );

            /**
             * Donde faltaba aplicar el AIU, el total solo puede subir.
             *
             * Con el interes constante no vale la misma regla: la tabla tiene
             * valores negativos para los periodos cortos (hasta -17,6 por
             * ciento a tres anos), asi que corregirlo puede abaratar el
             * activo con toda legitimidad.
             */
            if (in_array($id, self::BANDERA_NUMERICA, true)) {
                $this->assertGreaterThan(
                    $esperado,
                    $obtenido,
                    "Cotizacion {$id}: aplicar el AIU que faltaba deberia subir el total."
                );
            }

            return;
        }

        $this->assertLessThan(
            0.01,
            $desviacion,
            "Cotizacion {$id}: el total facturado cambio "
            . '(' . number_format($esperado, 2) . ' -> ' . number_format($obtenido, 2) . ').'
        );
    }

    /**
     * La suma de los items cuadra con el subtotal.
     *
     * Con el AIU incorporado deben coincidir; con el AIU desglosado la
     * diferencia es exactamente A+I+U. Este fue el error que reporto el area
     * comercial sobre la cotizacion 55.
     */
    #[DataProvider('cotizacionesConCosto')]
    public function test_la_suma_de_items_cuadra_con_el_subtotal(int $id): void
    {
        $cotizacion = Cotizacion::query()->with('items')->findOrFail($id);

        $resultado = CalcularCotizacion::crear()->para($cotizacion);

        $cantidades = $cotizacion->items
            ->pluck('cantidad', 'id')
            ->map(static fn ($c): int => max(1, (int) $c))
            ->all();

        $suma = $resultado->sumaDeItems($cantidades);

        $esperada = $resultado->parametros->seAplicaPorItem()
            ? $resultado->totales->subtotal
            : $resultado->totales->costoDirecto;

        $this->assertEqualsWithDelta(
            $esperada,
            $suma,
            max(1.0, $esperada * 0.0001),
            "Cotizacion {$id}: la suma de los items no cuadra con el subtotal."
        );
    }

    /** El desglose del AIU suma lo que el subtotal contiene. */
    #[DataProvider('cotizacionesConCosto')]
    public function test_el_desglose_del_aiu_cuadra(int $id): void
    {
        $resultado = CalcularCotizacion::crear()->para(Cotizacion::findOrFail($id));

        $this->assertEqualsWithDelta(
            $resultado->totales->subtotal,
            $resultado->totales->costoDirecto + $resultado->totales->aiuEnPesos(),
            max(0.02, $resultado->totales->subtotal * 0.0000001),
            "Cotizacion {$id}: costo directo mas AIU no da el subtotal."
        );
    }

    /** El costo se agrupa por categoria de recurso. */
    public function test_agrupa_el_costo_por_categoria(): void
    {
        $resultado = CalcularCotizacion::crear()->para(Cotizacion::findOrFail(44));

        $categorias = $resultado->costoPorCategoria();

        $this->assertNotEmpty($categorias);

        $this->assertEqualsWithDelta(
            array_sum($resultado->costoPorItem),
            array_sum($categorias),
            0.02,
            'Las categorias no suman el costo directo total.'
        );
    }

    /**
     * Una cotizacion con recursos no calculables lo reporta.
     *
     * La 7 tiene quince recursos cargados y unidades que no permiten
     * calcular un precio. El sistema actual devuelve cero en silencio.
     */
    public function test_reporta_los_recursos_que_no_se_pudieron_calcular(): void
    {
        $resultado = CalcularCotizacion::crear()->para(Cotizacion::findOrFail(7));

        $this->assertTrue($resultado->tieneAvisos());
        $this->assertNotEmpty($resultado->avisos);
    }

    /** Una cotizacion normal no reporta nada. */
    public function test_una_cotizacion_normal_no_reporta_avisos(): void
    {
        $resultado = CalcularCotizacion::crear()->para(Cotizacion::findOrFail(55));

        $this->assertFalse(
            $resultado->tieneAvisos(),
            'Avisos inesperados: ' . implode(' | ', $resultado->avisos)
        );
    }

    /**
     * Calcular una cotizacion no dispara una consulta por recurso.
     *
     * El sistema actual sufre justo eso: 135 consultas sueltas en el
     * componente de cotizacion. Con 345 revisiones, una por fila serian
     * cientos de viajes a la base en cada render.
     */
    public function test_no_dispara_una_consulta_por_recurso(): void
    {
        $cotizacion = Cotizacion::query()->with(['items', 'aiu'])->findOrFail(55);

        $consultas = 0;
        DB::listen(function () use (&$consultas): void {
            $consultas++;
        });

        CalcularCotizacion::crear()->para($cotizacion);

        /**
         * El limite es generoso a proposito: lo que se vigila es que no
         * crezca con el numero de revisiones, no el numero exacto.
         */
        $this->assertLessThan(
            150,
            $consultas,
            "Calcular la cotizacion 55 disparo {$consultas} consultas: revisar el eager loading."
        );
    }

    /** El resultado se puede serializar para una pantalla o un informe. */
    public function test_el_resultado_se_puede_serializar(): void
    {
        $datos = CalcularCotizacion::crear()->para(Cotizacion::findOrFail(55))->aArray();

        foreach (['cotizacion_id', 'escenario', 'totales', 'costo_por_item', 'costo_por_categoria'] as $clave) {
            $this->assertArrayHasKey($clave, $datos);
        }

        $this->assertIsNumeric($datos['totales']['total']);
        $this->assertNotEmpty(json_encode($datos));
    }
}
