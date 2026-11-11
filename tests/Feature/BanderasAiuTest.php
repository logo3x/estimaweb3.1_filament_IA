<?php

namespace Tests\Feature;

use App\Dominio\Aiu\ParametrosAiu;
use App\Models\Aiu;
use Tests\TestCase;

/**
 * Las banderas del AIU se leen bien vengan en el formato que vengan.
 *
 * En la tabla aius conviven DOS formatos para el mismo dato: la mayoria de
 * las filas guardan 'SI'/'NO' y unas pocas guardan '1'/'0'. El sistema actual
 * solo entiende el primero, asi que una cotizacion guardada como '1' se
 * calcula como si dijera 'NO'.
 *
 * Es el mismo patron que ya aparecio en f_prestacional y aux_educacion: un
 * dato que se guarda en un formato y se lee en otro. Ninguno de los tres se
 * habria encontrado sin ejecutar el calculo sobre datos reales.
 */
class BanderasAiuTest extends TestCase
{
    /** El formato de texto, que es el mayoritario. */
    public function test_lee_las_banderas_en_formato_texto(): void
    {
        $aiu = new Aiu(['contrato_obra' => 'SI', 'mostrar_aiu' => 'NO']);

        $this->assertTrue($aiu->esContratoDeObra());
        $this->assertFalse($aiu->muestraAiuDesglosado());
    }

    /**
     * El formato numerico, que el sistema actual ignora.
     *
     * Aqui esta el fallo corregido: '1' significa SI, no NO.
     */
    public function test_lee_las_banderas_en_formato_numerico(): void
    {
        $aiu = new Aiu(['contrato_obra' => '0', 'mostrar_aiu' => '1']);

        $this->assertFalse($aiu->esContratoDeObra());
        $this->assertTrue(
            $aiu->muestraAiuDesglosado(),
            'Una bandera guardada como "1" debe entenderse como afirmativa.'
        );
    }

    /** Tolera variaciones de escritura y espacios sobrantes. */
    public function test_tolera_variaciones_de_escritura(): void
    {
        foreach (['si', 'Si', 'SI', ' SI ', 'sí', '1'] as $afirmativo) {
            $this->assertTrue(
                (new Aiu(['mostrar_aiu' => $afirmativo]))->muestraAiuDesglosado(),
                "El valor '{$afirmativo}' deberia leerse como afirmativo."
            );
        }

        foreach (['no', 'NO', '0', '', 'cualquier cosa'] as $negativo) {
            $this->assertFalse(
                (new Aiu(['mostrar_aiu' => $negativo]))->muestraAiuDesglosado(),
                "El valor '{$negativo}' no deberia leerse como afirmativo."
            );
        }
    }

    /** Una bandera sin valor no es afirmativa. */
    public function test_una_bandera_nula_no_es_afirmativa(): void
    {
        $aiu = new Aiu(['contrato_obra' => null, 'mostrar_aiu' => null]);

        $this->assertFalse($aiu->esContratoDeObra());
        $this->assertFalse($aiu->muestraAiuDesglosado());
    }

    /**
     * Los parametros del dominio heredan la lectura correcta.
     *
     * Es lo que importa: que el escenario con que se calcula sea el que el
     * usuario eligio, no el que el formato permitio leer.
     */
    public function test_los_parametros_del_dominio_respetan_el_formato_numerico(): void
    {
        $aiu = new Aiu([
            'administracion' => 15.0,
            'imprevistos' => 3.0,
            'utilidad' => 7.0,
            'contrato_obra' => '0',
            'mostrar_aiu' => '1',
        ]);

        $parametros = ParametrosAiu::desdeModelo($aiu);

        $this->assertSame('servicio/aiu-visible', $parametros->escenario());
        $this->assertFalse($parametros->seAplicaPorItem());
    }

    /**
     * Las cotizaciones reales con formato numerico cambian de escenario.
     *
     * Deja constancia de que el dominio nuevo las interpreta distinto del
     * sistema actual, y de que eso es deliberado.
     */
    public function test_las_cotizaciones_afectadas_cambian_de_escenario(): void
    {
        foreach ([6, 7] as $id) {
            $aiu = Aiu::query()->where('id_cotizacion', $id)->first();

            $this->assertNotNull($aiu, "Falta el AIU de la cotizacion {$id}.");

            $this->assertTrue(
                $aiu->muestraAiuDesglosado(),
                "La cotizacion {$id} pidio el AIU desglosado y debe leerse asi."
            );
        }
    }
}
