<?php

namespace Tests\Feature;

use App\Dominio\Documentos\ConversorAPdf;
use App\Dominio\Documentos\GeneradorDeOferta;
use App\Models\Cotizacion;
use RuntimeException;
use Tests\TestCase;

/**
 * Conversion de la oferta a PDF.
 *
 * El sistema actual abre Microsoft Word en el servidor para exportar, lo que
 * solo funciona en Windows con Word instalado. El alojamiento de destino es
 * cPanel compartido, asi que la conversion tiene que ser PHP puro.
 *
 * Estas pruebas vigilan justamente eso: que no reaparezca ninguna dependencia
 * del sistema operativo.
 */
class ConversorAPdfTest extends TestCase
{
    /** @var list<string> */
    private array $generados = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(storage_path('app/plantillas/oferta.docx'))) {
            $this->artisan('plantilla:oferta');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->generados as $archivo) {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }

        parent::tearDown();
    }

    private function convertir(Cotizacion $cotizacion): string
    {
        $pdf = ConversorAPdf::crear()->desdeCotizacion($cotizacion);

        $this->generados[] = $pdf;

        return $pdf;
    }

    /** Se genera un PDF valido. */
    public function test_genera_un_pdf_valido(): void
    {
        $pdf = $this->convertir(Cotizacion::findOrFail(55));

        $this->assertFileExists($pdf);
        $this->assertGreaterThan(1000, filesize($pdf));

        $this->assertStringStartsWith(
            '%PDF',
            (string) file_get_contents($pdf),
            'El archivo no empieza por la firma de un PDF.'
        );
    }

    /**
     * El contenido de la oferta llega al PDF.
     *
     * El texto va comprimido dentro del archivo, asi que hay que
     * descomprimirlo para comprobarlo: que el PDF exista no garantiza que
     * tenga nada dentro.
     */
    public function test_el_contenido_llega_al_pdf(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $texto = $this->textoDe($this->convertir($cotizacion));

        $this->assertStringContainsString((string) $cotizacion->cliente, $texto);
        $this->assertStringContainsString('VALOR TOTAL', $texto);
        $this->assertStringContainsString('IVA', $texto);
    }

    /** El total del PDF es el que calcula el dominio. */
    public function test_el_total_del_pdf_sale_del_dominio(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $esperado = \App\Dominio\CalcularCotizacion::crear()
            ->para($cotizacion)
            ->totales
            ->total;

        $this->assertStringContainsString(
            number_format($esperado, 2, ',', '.'),
            $this->textoDe($this->convertir($cotizacion)),
            'El total del PDF no coincide con el que calcula el dominio.'
        );
    }

    /**
     * EL PUNTO CRITICO: la conversion no depende del sistema operativo.
     *
     * El sistema actual usa new COM("Word.Application"), que solo existe en
     * Windows con Word instalado. En cPanel compartido no hay ninguna de las
     * dos cosas, asi que si eso reaparece el despliegue se rompe.
     */
    public function test_la_conversion_no_depende_del_sistema_operativo(): void
    {
        /**
         * Se analizan los tokens de PHP y no el texto del archivo: los
         * comentarios explican que hacia el sistema actual y mencionan esas
         * llamadas, de modo que una busqueda de texto daria un falso aviso.
         */
        $tokens = token_get_all(
            (string) file_get_contents(app_path('Dominio/Documentos/ConversorAPdf.php'))
        );

        $codigo = '';

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $codigo .= is_array($token) ? $token[1] : $token;
        }

        foreach (['COM', 'Word.Application', 'exec', 'shell_exec', 'proc_open', 'soffice'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $codigo,
                "El conversor usa '{$prohibido}', que no existe en un hosting compartido."
            );
        }
    }

    /** El .docx intermedio no se queda por ahi. */
    public function test_no_deja_el_documento_intermedio(): void
    {
        $antes = glob(storage_path('app/ofertas/*.docx')) ?: [];

        $this->convertir(Cotizacion::findOrFail(55));

        $despues = glob(storage_path('app/ofertas/*.docx')) ?: [];

        $this->assertCount(
            count($antes),
            $despues,
            'La conversion dejo un .docx sin borrar.'
        );
    }

    /** Un documento inexistente da un error claro. */
    public function test_un_documento_inexistente_avisa_con_claridad(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se encuentra el documento');

        ConversorAPdf::crear()->desdeDocx(storage_path('app/ofertas/no-existe.docx'));
    }

    /** Se puede convertir un .docx ya generado. */
    public function test_convierte_un_documento_existente(): void
    {
        $docx = GeneradorDeOferta::crear()->generar(Cotizacion::findOrFail(55));
        $this->generados[] = $docx;

        $pdf = ConversorAPdf::crear()->desdeDocx($docx);
        $this->generados[] = $pdf;

        $this->assertFileExists($pdf);
        $this->assertFileExists($docx, 'Convertir no deberia borrar el documento original.');
    }

    /**
     * Los caracteres especiales tampoco rompen el PDF.
     *
     * El ampersand corrompia el .docx; conviene comprobar que la conversion
     * posterior tampoco tropieza con el.
     */
    public function test_los_caracteres_especiales_no_rompen_el_pdf(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);
        $original = $cotizacion->cliente;

        $cotizacion->update(['cliente' => 'Mantenimiento & Operaciones <Zona 1>']);

        try {
            $pdf = $this->convertir($cotizacion->fresh());

            $this->assertStringStartsWith('%PDF', (string) file_get_contents($pdf));
        } finally {
            $cotizacion->update(['cliente' => $original]);
        }
    }

    /**
     * Extrae el texto de un PDF.
     *
     * Va comprimido en streams, de modo que hay que descomprimirlo.
     */
    private function textoDe(string $pdf): string
    {
        $contenido = (string) file_get_contents($pdf);

        preg_match_all('/stream\r?\n(.*?)endstream/s', $contenido, $coincidencias);

        $texto = '';

        foreach ($coincidencias[1] as $stream) {
            $descomprimido = @gzuncompress(trim($stream));

            if ($descomprimido !== false) {
                $texto .= $descomprimido;
            }
        }

        return $texto;
    }
}
