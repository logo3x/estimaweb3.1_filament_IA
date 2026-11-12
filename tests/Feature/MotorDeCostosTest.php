<?php

namespace Tests\Feature;

use App\Dominio\Nomina\CalculadoraNomina;
use App\Dominio\Nomina\FactoresLegales;
use App\Dominio\Recursos\CalculadoraAmortizacion;
use App\Dominio\Recursos\MotorDeCostos;
use App\Models\Cotizacion;
use App\Models\Revision;
use Tests\TestCase;

/**
 * El motor que recorre los recursos y acumula el costo de cada item.
 *
 * Reemplaza los once metodos procesarX del sistema actual, que al mirarlos de
 * cerca solo tienen DOS comportamientos: los recursos que cobran por tiempo,
 * donde la unidad decide el precio, y los que cobran por cantidad, donde la
 * unidad es una etiqueta.
 */
class MotorDeCostosTest extends TestCase
{
    private function motor(): MotorDeCostos
    {
        return new MotorDeCostos(
            new CalculadoraNomina(FactoresLegales::vigentes()),
            new CalculadoraAmortizacion,
        );
    }

    /**
     * @return array{\Illuminate\Support\Collection<int, Revision>, array<int, int>}
     */
    private function datosDe(int $cotizacionId): array
    {
        $cotizacion = Cotizacion::query()->with('items')->findOrFail($cotizacionId);

        $revisiones = Revision::query()
            ->where('id_cotizacion', $cotizacionId)
            ->get();

        $cantidades = $cotizacion->items
            ->pluck('cantidad', 'id')
            ->map(static fn ($cantidad): int => (int) $cantidad)
            ->all();

        return [$revisiones, $cantidades];
    }

    /**
     * LA PRUEBA CENTRAL: la cotizacion 7 deja de valer cero.
     *
     * Tiene quince recursos bien cargados -- salarios de 3,2 y 5,5 millones --
     * y el sistema actual le da un costo de cero pesos, porque sus unidades
     * ("Persona", "Unidad") no entran en ninguna de las ramas del calculo.
     *
     * El motor nuevo tampoco puede inventar un precio para esas unidades,
     * pero si deja constancia de que quedaron fuera en lugar de callarlo.
     */
    public function test_la_cotizacion_7_avisa_de_sus_unidades_no_calculables(): void
    {
        [$revisiones, $cantidades] = $this->datosDe(7);

        $resultado = $this->motor()->calcular($revisiones, $cantidades);

        $this->assertNotEmpty(
            $resultado['avisos'],
            'La cotizacion 7 deberia avisar de los recursos que no se pueden calcular.'
        );

        /** Doce de sus quince recursos cobran por tiempo y traen unidad invalida. */
        $this->assertCount(12, $resultado['avisos']);

        foreach ($resultado['avisos'] as $aviso) {
            $this->assertStringContainsString('Aporta cero', $aviso);
        }
    }

    /**
     * Una cotizacion con unidades correctas no genera ningun aviso.
     *
     * Confirma que el aviso senala un problema real y no aparece siempre.
     */
    public function test_una_cotizacion_normal_no_genera_avisos(): void
    {
        [$revisiones, $cantidades] = $this->datosDe(55);

        $resultado = $this->motor()->calcular($revisiones, $cantidades);

        $this->assertSame(
            [],
            $resultado['avisos'],
            'La cotizacion 55 no deberia tener recursos sin calcular: ' . implode(' | ', $resultado['avisos'])
        );
    }

    /** Cada item acumula el costo de los recursos que le fueron asignados. */
    public function test_acumula_el_costo_por_item(): void
    {
        [$revisiones, $cantidades] = $this->datosDe(55);

        $resultado = $this->motor()->calcular($revisiones, $cantidades);

        $this->assertNotEmpty($resultado['costos']);

        foreach ($resultado['costos'] as $itemId => $costo) {
            $this->assertArrayHasKey($itemId, $cantidades, "El item {$itemId} no pertenece a la cotizacion.");
            $this->assertGreaterThan(0, $costo, "El item {$itemId} quedo en cero.");
        }
    }

    /** El detalle suma exactamente el costo acumulado de cada item. */
    public function test_el_detalle_cuadra_con_los_totales(): void
    {
        [$revisiones, $cantidades] = $this->datosDe(55);

        $resultado = $this->motor()->calcular($revisiones, $cantidades);

        $sumaPorItem = [];

        foreach ($resultado['lineas'] as $linea) {
            $sumaPorItem[$linea->itemId] = ($sumaPorItem[$linea->itemId] ?? 0.0) + $linea->aporte;
        }

        foreach ($resultado['costos'] as $itemId => $costo) {
            $this->assertEqualsWithDelta(
                $costo,
                $sumaPorItem[$itemId] ?? 0.0,
                0.01,
                "El detalle del item {$itemId} no suma su costo acumulado."
            );
        }
    }

    /**
     * El aporte de una linea es el valor unitario por las dos cantidades.
     *
     * La de la revision (cuantas unidades del recurso) y la del item (cuantas
     * veces se repite el item en la oferta).
     */
    public function test_el_aporte_multiplica_por_las_dos_cantidades(): void
    {
        [$revisiones, $cantidades] = $this->datosDe(55);

        $resultado = $this->motor()->calcular($revisiones, $cantidades);

        $this->assertNotEmpty($resultado['lineas']);

        foreach (array_slice($resultado['lineas'], 0, 10) as $linea) {
            $esperado = $linea->valorUnitario * $linea->cantidad * ($cantidades[$linea->itemId] ?? 1);

            $this->assertEqualsWithDelta(
                $esperado,
                $linea->aporte,
                0.01,
                "La linea '{$linea->descripcion}' no multiplica bien las cantidades."
            );
        }
    }

    /**
     * Los recursos por cantidad no dependen de la unidad.
     *
     * Vehiculos, servicios, viaticos y otros guardan su valor y lo usan tal
     * cual; por eso "N/A" en esas categorias nunca fue un problema.
     */
    public function test_los_recursos_por_cantidad_no_dependen_de_la_unidad(): void
    {
        $revisiones = Revision::query()
            ->whereNotNull('id_viatico')
            ->where('unidad', 'N/A')
            ->limit(5)
            ->get();

        $this->assertNotEmpty($revisiones, 'Se esperaban viaticos con unidad N/A.');

        $cantidades = [];

        foreach ($revisiones as $revision) {
            $cantidades[(int) $revision->id_item_cotizacion] = 1;
        }

        $resultado = $this->motor()->calcular($revisiones, $cantidades);

        $this->assertSame(
            [],
            $resultado['avisos'],
            'Los viaticos con N/A no deberian generar avisos: no cobran por tiempo.'
        );
    }

    /** Una revision sin recurso asignado se ignora sin romper el calculo. */
    public function test_una_revision_sin_recurso_no_rompe_el_calculo(): void
    {
        $revision = new Revision(['id_item_cotizacion' => 1, 'cantidad' => 1, 'unidad' => 'Mes']);
        $revision->id = 999999;

        $resultado = $this->motor()->calcular(collect([$revision]), [1 => 1]);

        $this->assertSame([], $resultado['costos']);
        $this->assertSame([], $resultado['lineas']);
    }

    /**
     * Las categorias del detalle corresponden a los tipos de recurso.
     */
    public function test_las_lineas_traen_su_categoria(): void
    {
        [$revisiones, $cantidades] = $this->datosDe(44);

        $resultado = $this->motor()->calcular($revisiones, $cantidades);

        $categorias = array_unique(array_map(
            static fn ($linea): string => $linea->categoria,
            $resultado['lineas']
        ));

        $this->assertNotEmpty($categorias);

        foreach ($categorias as $categoria) {
            $this->assertContains($categoria, [
                'Personal', 'Herramienta', 'Equipo', 'Consumible', 'Vehiculo',
                'Oficina', 'Aseo', 'Servicio', 'Viatico', 'Comunicacion', 'Otros',
            ], "Categoria inesperada: {$categoria}");
        }
    }

    /**
     * Cotizaciones cuyo costo cambia a proposito respecto al sistema actual.
     *
     * Son las que tienen herramientas o equipos: alli se aplica el interes
     * constante que el sistema actual nunca llego a encontrar en su tabla.
     * Decidido con el usuario el 2026-11-11.
     *
     * @var list<int>
     */
    private const CON_INTERES_CORREGIDO = [24, 26, 27, 29, 44, 45, 47];

    /**
     * @return array<string, array{int}>
     */
    public static function cotizacionesConCosto(): array
    {
        $casos = [];

        foreach (glob(__DIR__ . '/../golden/cotizacion-*.json') ?: [] as $archivo) {
            $datos = json_decode((string) file_get_contents($archivo), true);

            if (! is_array($datos)) {
                continue;
            }

            $costo = array_sum(array_column($datos['items'], 'costo_directo'));

            if ($costo <= 0) {
                continue;
            }

            $casos["cotizacion {$datos['cotizacion_id']}"] = [(int) $datos['cotizacion_id']];
        }

        return $casos;
    }

    /**
     * EL CONTRASTE CENTRAL: el costo directo coincide con el sistema actual.
     *
     * Treinta y una cotizaciones reales dan exactamente el mismo costo. Las
     * siete restantes difieren A PROPOSITO, por el interes constante que el
     * sistema actual nunca aplico, y para ellas se comprueba que la
     * diferencia se mantenga dentro de lo razonable.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cotizacionesConCosto')]
    public function test_el_costo_directo_coincide_con_el_sistema_actual(int $id): void
    {
        $golden = json_decode(
            (string) file_get_contents(__DIR__ . "/../golden/cotizacion-{$id}.json"),
            true
        );

        $esperado = (float) array_sum(array_column($golden['items'], 'costo_directo'));

        [$revisiones, $cantidades] = $this->datosDe($id);

        $obtenido = array_sum($this->motor()->calcular($revisiones, $cantidades)['costos']);

        $desviacion = abs($obtenido - $esperado) / $esperado * 100;

        if (in_array($id, self::CON_INTERES_CORREGIDO, true)) {
            $this->assertGreaterThan(
                0.01,
                $desviacion,
                "Cotizacion {$id}: deberia diferir, porque aqui se corrige el interes constante."
            );

            /** La correccion afecta solo a una parte del costo, no lo dispara. */
            $this->assertLessThan(
                10.0,
                $desviacion,
                "Cotizacion {$id}: la correccion del interes no deberia mover tanto el costo."
            );

            return;
        }

        $this->assertLessThan(
            0.01,
            $desviacion,
            "Cotizacion {$id}: el costo directo cambio respecto al sistema actual "
            . '(' . number_format($esperado, 2) . ' -> ' . number_format($obtenido, 2) . ').'
        );
    }

    /**
     * Las cotizaciones afectadas por el interes son las que tienen activos.
     *
     * Si aparece una fuera de esa lista, la causa seria otra y habria que
     * investigarla.
     */
    public function test_solo_difieren_las_cotizaciones_con_herramientas_o_equipos(): void
    {
        foreach (self::CON_INTERES_CORREGIDO as $id) {
            $activos = Revision::query()
                ->where('id_cotizacion', $id)
                ->where(function ($query): void {
                    $query->whereNotNull('id_herramienta')->orWhereNotNull('id_equipo');
                })
                ->count();

            $this->assertGreaterThan(
                0,
                $activos,
                "La cotizacion {$id} difiere pero no tiene herramientas ni equipos."
            );
        }
    }
}
