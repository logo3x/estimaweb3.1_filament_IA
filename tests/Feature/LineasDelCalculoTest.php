<?php

namespace Tests\Feature;

use App\Dominio\CalcularCotizacion;
use App\Dominio\Nomina\CalculadoraNomina;
use App\Dominio\Nomina\DatosPersona;
use App\Dominio\Nomina\FactoresLegales;
use App\Models\Cotizacion;
use App\Models\Personal;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El calculo coincide con el del sistema actual LINEA POR LINEA.
 *
 * Los golden masters de cotizacion-*.json vigilan totales e items. Un error
 * en una linea puede quedar escondido en un total si otra linea lo compensa,
 * o si el margen que se tolera por el interes constante lo absorbe. Aqui se
 * compara cada revision (lo que aporta cada persona, herramienta, equipo,
 * consumible...) y el costo de cada persona, al centavo.
 *
 * Las unicas lineas que pueden diferir son las de `diferencias_deliberadas`,
 * y para ellas se exige el valor calculado A MANO al generar el archivo, no
 * cualquier valor distinto. Ver tests/golden/generar-lineas.php.
 */
class LineasDelCalculoTest extends TestCase
{
    private const CENTAVO = 0.01;

    /** @return array<string, mixed> */
    private static function golden(): array
    {
        static $datos = null;

        return $datos ??= json_decode(
            (string) file_get_contents(__DIR__.'/../golden/lineas.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, array{int}> */
    public static function cotizaciones(): array
    {
        $casos = [];

        foreach (array_keys(self::golden()['cotizaciones']) as $id) {
            $casos["cotizacion {$id}"] = [(int) $id];
        }

        return $casos;
    }

    #[DataProvider('cotizaciones')]
    public function test_cada_linea_coincide_con_el_sistema_actual(int $id): void
    {
        $congelada = self::golden()['cotizaciones'][(string) $id];
        $deliberadas = self::golden()['diferencias_deliberadas'];

        $calculo = CalcularCotizacion::crear()->para(Cotizacion::query()->findOrFail($id));

        $nuevas = [];
        foreach ($calculo->detalle as $linea) {
            $nuevas[$linea->revisionId] = $linea;
        }

        $this->assertCount(
            count($congelada['lineas']),
            $nuevas,
            "Cotizacion {$id}: el sistema actual calcula ".count($congelada['lineas']).' lineas y el 3.1 '.count($nuevas).'.'
        );

        foreach ($congelada['lineas'] as $esperada) {
            $revision = $esperada['revision'];
            $this->assertArrayHasKey($revision, $nuevas, "Cotizacion {$id}: falta la linea de la revision {$revision}.");

            $nueva = $nuevas[$revision];
            $donde = "Cotizacion {$id}, revision {$revision} ({$esperada['categoria']}, {$esperada['cantidad']} {$esperada['unidad']})";

            $this->assertSame($esperada['item'], $nueva->itemId, "{$donde}: cambio de item.");
            $this->assertEqualsWithDelta($esperada['cantidad'], $nueva->cantidad, 1e-9, "{$donde}: cambio la cantidad.");

            $objetivo = $deliberadas[(string) $revision] ?? $esperada;

            $this->assertEqualsWithDelta(
                $objetivo['valor_unitario'],
                $nueva->valorUnitario,
                self::CENTAVO,
                "{$donde}: valor unitario distinto.".(isset($deliberadas[(string) $revision]) ? ' Motivo esperado: '.$objetivo['motivo'] : '')
            );

            $this->assertEqualsWithDelta(
                $objetivo['aporte'],
                $nueva->aporte,
                self::CENTAVO,
                "{$donde}: aporte al item distinto."
            );
        }
    }

    #[DataProvider('cotizaciones')]
    public function test_el_costo_de_cada_persona_coincide(int $id): void
    {
        $personas = self::golden()['cotizaciones'][(string) $id]['personas'];

        if ($personas === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $nomina = new CalculadoraNomina(FactoresLegales::vigentes());

        foreach ($personas as $personaId => $esperado) {
            $costo = $nomina->calcular(DatosPersona::desdeModelo(Personal::query()->findOrFail($personaId)));

            $this->assertEqualsWithDelta($esperado['por_mes'], $costo->porMes, self::CENTAVO, "Persona {$personaId}: costo por mes.");
            $this->assertEqualsWithDelta($esperado['por_dia_calendario'], $costo->porDiaCalendario, self::CENTAVO, "Persona {$personaId}: costo por dia calendario.");
            $this->assertEqualsWithDelta($esperado['por_dia_habil'], $costo->porDiaHabil, self::CENTAVO, "Persona {$personaId}: costo por dia habil.");
        }
    }

    /**
     * Cada diferencia deliberada difiere de verdad del sistema actual.
     *
     * Si una dejara de diferir, la lista estaria tapando lineas que ya no lo
     * necesitan: hay que regenerar y revisar.
     */
    public function test_las_diferencias_deliberadas_son_reales_y_estan_explicadas(): void
    {
        $golden = self::golden();
        $this->assertNotEmpty($golden['diferencias_deliberadas']);

        foreach ($golden['diferencias_deliberadas'] as $revision => $diferencia) {
            $original = collect($golden['cotizaciones'][(string) $diferencia['cotizacion']]['lineas'])
                ->firstWhere('revision', (int) $revision);

            $this->assertNotNull($original, "La diferencia de la revision {$revision} no corresponde a ninguna linea.");
            $this->assertNotEmpty($diferencia['motivo'], "La diferencia de la revision {$revision} no explica su motivo.");
            $this->assertGreaterThan(
                self::CENTAVO,
                abs($original['valor_unitario'] - $diferencia['valor_unitario']),
                "La revision {$revision} ya no difiere del sistema actual: sobra en la lista."
            );
        }
    }

    /** El archivo cubre todas las cotizaciones, para que ninguna quede sin vigilar. */
    public function test_cubre_todas_las_cotizaciones(): void
    {
        $this->assertSame(
            Cotizacion::query()->orderBy('id')->pluck('id')->map(fn ($id): string => (string) $id)->all(),
            array_map('strval', array_keys(self::golden()['cotizaciones'])),
        );
    }
}
