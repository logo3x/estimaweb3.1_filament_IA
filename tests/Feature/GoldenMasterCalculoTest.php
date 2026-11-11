<?php

namespace Tests\Feature;

use App\Models\Aiu;
use App\Models\Cotizacion;
use Tests\TestCase;

/**
 * Referencia congelada del calculo del sistema actual.
 *
 * Los archivos de tests/golden guardan, para cada cotizacion real, los totales
 * y el valor unitario de cada item tal como los produce el sistema en
 * produccion. El dominio nuevo tendra que reproducirlos exactamente.
 *
 * Mientras ese dominio no exista, estas pruebas verifican que la referencia
 * siga siendo valida: que los snapshots esten completos, que correspondan a
 * las cotizaciones cargadas y que sean internamente coherentes. En cuanto
 * exista el calculo nuevo, se le anade aqui la comparacion contra estos
 * mismos numeros.
 *
 * Se regeneran desde el sistema actual con `php artisan golden:generar`.
 */
class GoldenMasterCalculoTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     *
     * La ruta se arma desde __DIR__ y no con base_path(): los data providers
     * corren antes de que la aplicacion arranque.
     */
    public static function cotizacionesCongeladas(): array
    {
        $archivos = glob(__DIR__ . '/../golden/cotizacion-*.json') ?: [];

        $casos = [];

        foreach ($archivos as $archivo) {
            $datos = json_decode((string) file_get_contents($archivo), true);

            if (! is_array($datos)) {
                continue;
            }

            $id = (int) $datos['cotizacion_id'];
            $casos["cotizacion {$id} ({$datos['escenario']})"] = [$id, $archivo];
        }

        return $casos;
    }

    /** Hay referencia para todas las cotizaciones cargadas. */
    public function test_existe_un_snapshot_por_cada_cotizacion(): void
    {
        $congeladas = array_map(
            static fn (array $caso): int => $caso[0],
            array_values(self::cotizacionesCongeladas())
        );

        sort($congeladas);

        $enBase = Cotizacion::query()->orderBy('id')->pluck('id')->all();

        $this->assertNotEmpty($congeladas, 'No hay snapshots en tests/golden.');

        $sinSnapshot = array_diff($enBase, $congeladas);

        $this->assertSame(
            [],
            array_values($sinSnapshot),
            'Hay cotizaciones sin referencia congelada: ' . implode(', ', $sinSnapshot)
        );
    }

    /**
     * El escenario guardado corresponde a los parametros que hay en la base.
     *
     * Si alguien cambia el AIU de una cotizacion, la referencia deja de ser
     * valida para ella y hay que regenerarla a proposito.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cotizacionesCongeladas')]
    public function test_el_escenario_del_snapshot_sigue_vigente(int $id, string $archivo): void
    {
        $esperado = json_decode((string) file_get_contents($archivo), true);

        $aiu = Aiu::query()->where('id_cotizacion', $id)->first();

        $esObra = $aiu !== null && $aiu->esContratoDeObra();
        $muestra = $aiu !== null && $aiu->muestraAiuDesglosado();

        $escenario = ($esObra ? 'obra' : 'servicio') . '/' . ($muestra ? 'aiu-visible' : 'aiu-oculto');

        $this->assertSame(
            $esperado['escenario'],
            $escenario,
            "Cotizacion {$id}: cambiaron los parametros del AIU en la base; el snapshot quedo obsoleto."
        );
    }

    /**
     * Los items del snapshot son los que tiene la cotizacion.
     *
     * Detecta que se haya anadido o borrado un item despues de congelar.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cotizacionesCongeladas')]
    public function test_los_items_del_snapshot_siguen_existiendo(int $id, string $archivo): void
    {
        $esperado = json_decode((string) file_get_contents($archivo), true);

        $cotizacion = Cotizacion::query()->with('items:id,id_cotizacion')->find($id);

        $this->assertNotNull($cotizacion, "La cotizacion {$id} ya no existe.");

        $enBase = $cotizacion->items->pluck('id')->sort()->values()->all();

        $congelados = array_map(
            static fn (array $item): int => (int) $item['id'],
            $esperado['items']
        );

        sort($congelados);

        $this->assertSame(
            $congelados,
            $enBase,
            "Cotizacion {$id}: cambiaron sus items respecto al snapshot."
        );
    }

    /**
     * La suma de los items cuadra con el subtotal dentro del propio snapshot.
     *
     * Con el AIU oculto deben coincidir; con el AIU visible la diferencia es
     * exactamente A+I+U, porque alli los items muestran el costo directo.
     * Esta fue la verificacion que detecto el error reportado por el area
     * comercial, cuando el valor unitario traia el IVA dentro.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cotizacionesCongeladas')]
    public function test_el_snapshot_es_coherente_consigo_mismo(int $id, string $archivo): void
    {
        $datos = json_decode((string) file_get_contents($archivo), true);

        $suma = 0.0;

        foreach ($datos['items'] as $item) {
            $suma += (float) $item['valor_unitario'] * (int) $item['cantidad'];
        }

        $diferencia = round($suma - (float) $datos['totales']['con_aiu'], 2);

        $this->assertEqualsWithDelta(
            $datos['cuadre']['diferencia_esperada'],
            $diferencia,
            1.0,
            "Cotizacion {$id}: el snapshot no cuadra consigo mismo."
        );

        $this->assertTrue(
            $datos['cuadre']['cuadra'],
            "Cotizacion {$id}: el snapshot quedo marcado como descuadrado."
        );
    }

    /**
     * Los escenarios del AIU presentes en los datos reales.
     *
     * Falta obra/aiu-oculto: ninguna cotizacion real lo usa. Hay que cubrirlo
     * con un caso sintetico antes de dar por cerrada la congelacion, o un
     * error en esa combinacion pasaria inadvertido.
     */
    public function test_los_snapshots_cubren_los_escenarios_de_los_datos_reales(): void
    {
        $escenarios = [];

        foreach (self::cotizacionesCongeladas() as [, $archivo]) {
            $datos = json_decode((string) file_get_contents($archivo), true);
            $escenarios[$datos['escenario']] = ($escenarios[$datos['escenario']] ?? 0) + 1;
        }

        $this->assertArrayHasKey('servicio/aiu-oculto', $escenarios);
        $this->assertArrayHasKey('servicio/aiu-visible', $escenarios);
        $this->assertArrayHasKey('obra/aiu-visible', $escenarios);
    }

    /** Cada snapshot trae los campos que el dominio nuevo debera reproducir. */
    #[\PHPUnit\Framework\Attributes\DataProvider('cotizacionesCongeladas')]
    public function test_el_snapshot_tiene_la_estructura_esperada(int $id, string $archivo): void
    {
        $datos = json_decode((string) file_get_contents($archivo), true);

        $this->assertIsArray($datos, "Cotizacion {$id}: el snapshot no es un JSON valido.");

        foreach (['cotizacion_id', 'escenario', 'parametros_aiu', 'totales', 'items', 'cuadre'] as $clave) {
            $this->assertArrayHasKey($clave, $datos, "Cotizacion {$id}: falta '{$clave}'.");
        }

        foreach (['sin_aiu', 'con_aiu', 'con_iva', 'administracion', 'imprevistos', 'utilidad', 'iva'] as $total) {
            $this->assertArrayHasKey($total, $datos['totales'], "Cotizacion {$id}: falta el total '{$total}'.");
            $this->assertIsNumeric($datos['totales'][$total]);
        }
    }
}
