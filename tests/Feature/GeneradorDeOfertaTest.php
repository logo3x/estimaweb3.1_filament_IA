<?php

namespace Tests\Feature;

use App\Dominio\CalcularCotizacion;
use App\Dominio\Documentos\GeneradorDeOferta;
use App\Models\Cotizacion;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Generacion del documento de oferta.
 *
 * Un .docx es un ZIP con XML dentro: si ese XML queda mal formado, Word se
 * niega a abrir el archivo y el usuario solo ve un error. Paso en produccion
 * con un ampersand en el nombre de un cargo.
 */
class GeneradorDeOfertaTest extends TestCase
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

    private function generar(Cotizacion $cotizacion): string
    {
        $ruta = GeneradorDeOferta::crear()->generar($cotizacion);

        $this->generados[] = $ruta;

        return $ruta;
    }

    /** El XML interno de un .docx, que es donde esta el texto. */
    private function xmlDe(string $archivo): string
    {
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($archivo) === true, 'El archivo no es un .docx valido.');

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    private function textoDe(string $archivo): string
    {
        return strip_tags(str_replace('</w:t>', ' ', $this->xmlDe($archivo)));
    }

    /** Se genera un documento abrible. */
    public function test_genera_un_documento_valido(): void
    {
        $archivo = $this->generar(Cotizacion::findOrFail(55));

        $this->assertFileExists($archivo);
        $this->assertGreaterThan(5000, filesize($archivo));

        $this->assertNotFalse(
            simplexml_load_string($this->xmlDe($archivo)),
            'El XML del documento esta mal formado: Word no podria abrirlo.'
        );
    }

    /** No queda ningun marcador sin rellenar. */
    public function test_no_quedan_marcadores_sin_rellenar(): void
    {
        $texto = $this->textoDe($this->generar(Cotizacion::findOrFail(55)));

        preg_match_all('/\$\{(\w+)\}/', $texto, $coincidencias);

        $this->assertSame(
            [],
            array_unique($coincidencias[1]),
            'Hay marcadores de plantilla sin rellenar.'
        );
    }

    /** Los datos del cliente llegan al documento. */
    public function test_incluye_los_datos_del_cliente(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);
        $texto = $this->textoDe($this->generar($cotizacion));

        $this->assertStringContainsString((string) $cotizacion->cliente, $texto);
        $this->assertStringContainsString((string) $cotizacion->codigo, $texto);
    }

    /**
     * El total del documento es el que calcula el dominio.
     *
     * No se lee de las columnas guardadas: muchas cotizaciones arrastran
     * totales de versiones anteriores del calculo.
     */
    public function test_el_total_sale_del_dominio(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $esperado = CalcularCotizacion::crear()->para($cotizacion)->totales->total;

        $texto = $this->textoDe($this->generar($cotizacion));

        $this->assertStringContainsString(
            number_format($esperado, 2, ',', '.'),
            $texto,
            'El total del documento no coincide con el que calcula el dominio.'
        );
    }

    /** La tabla trae sus encabezados y la fila de cierre. */
    public function test_la_tabla_tiene_encabezados_y_total(): void
    {
        $texto = $this->textoDe($this->generar(Cotizacion::findOrFail(55)));

        foreach (['ITEM', 'DESCRIPCIÓN', 'UNIDAD', 'CANTIDAD', 'VALOR UNITARIO'] as $encabezado) {
            $this->assertStringContainsString($encabezado, $texto, "Falta el encabezado {$encabezado}.");
        }

        $this->assertStringContainsString('COSTO TOTAL INCLUIDO IVA', $texto);
        $this->assertStringContainsString('IVA', $texto);
    }

    /**
     * Con el AIU desglosado aparecen sus tres lineas.
     *
     * Con el AIU incorporado no deben salir: el cliente no tiene que ver el
     * margen, y por eso se eligio ocultarlo.
     */
    public function test_el_desglose_del_aiu_depende_del_escenario(): void
    {
        $visible = Cotizacion::query()
            ->whereHas('aiu', fn ($q) => $q->where('mostrar_aiu', 'SI'))
            ->whereHas('items')
            ->firstOrFail();

        $texto = $this->textoDe($this->generar($visible));

        foreach (['Administración', 'Imprevistos', 'Utilidad', 'VALOR TOTAL CON AIU'] as $linea) {
            $this->assertStringContainsString($linea, $texto, "Falta la línea {$linea}.");
        }

        $oculto = Cotizacion::query()
            ->whereHas('aiu', fn ($q) => $q->where('mostrar_aiu', 'NO'))
            ->whereHas('items')
            ->firstOrFail();

        $this->assertStringNotContainsString(
            'VALOR TOTAL CON AIU',
            $this->textoDe($this->generar($oculto)),
            'Con el AIU oculto no debe aparecer su desglose.'
        );
    }

    /**
     * EL FALLO DE PRODUCCION: un ampersand no puede romper el documento.
     *
     * Un cliente llamado "Mantenimiento & Operaciones" dejaba el XML mal
     * formado y Word se negaba a abrir el archivo.
     *
     * @param  string  $peligroso  Texto con caracteres que rompen el XML
     */
    #[DataProvider('textosQueRompenElXml')]
    public function test_los_caracteres_especiales_no_rompen_el_documento(string $peligroso): void
    {
        $cotizacion = Cotizacion::findOrFail(55);
        $original = $cotizacion->cliente;

        $cotizacion->update(['cliente' => $peligroso]);

        try {
            $archivo = $this->generar($cotizacion->fresh());

            $this->assertNotFalse(
                simplexml_load_string($this->xmlDe($archivo)),
                "El texto «{$peligroso}» dejo el XML mal formado."
            );
        } finally {
            $cotizacion->update(['cliente' => $original]);
        }
    }

    /** @return array<string, array{string}> */
    public static function textosQueRompenElXml(): array
    {
        return [
            'ampersand' => ['Mantenimiento & Operaciones'],
            'menor que' => ['Presión < 100 psi'],
            'mayor que' => ['Caudal > 50 m3'],
            'comillas' => ['Servicio "especializado"'],
            'apostrofo' => ["O'Brien Ingeniería"],
            'todos juntos' => ['A & B <C> "D" \'E\''],
        ];
    }

    /** Las observaciones en HTML llegan sin romper nada. */
    public function test_las_observaciones_en_html_no_rompen_el_documento(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);
        $original = $cotizacion->observaciones;

        $cotizacion->update([
            'observaciones' => '<p>Primera línea con <strong>negrita</strong>.</p><ul><li>Punto uno</li><li>Punto dos</li></ul>',
        ]);

        try {
            $archivo = $this->generar($cotizacion->fresh());

            $this->assertNotFalse(simplexml_load_string($this->xmlDe($archivo)));
            $this->assertStringContainsString('Primera línea', $this->textoDe($archivo));
        } finally {
            $cotizacion->update(['observaciones' => $original]);
        }
    }

    /** Sin observaciones tampoco falla. */
    public function test_sin_observaciones_se_genera_igual(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);
        $original = $cotizacion->observaciones;

        $cotizacion->update(['observaciones' => '']);

        try {
            $archivo = $this->generar($cotizacion->fresh());

            $this->assertNotFalse(simplexml_load_string($this->xmlDe($archivo)));
        } finally {
            $cotizacion->update(['observaciones' => $original]);
        }
    }

    /** Una cotizacion sin items genera el documento con la tabla vacia. */
    public function test_una_cotizacion_sin_items_no_rompe_el_documento(): void
    {
        $vacia = Cotizacion::query()->doesntHave('items')->first();

        if ($vacia === null) {
            $this->markTestSkipped('No hay cotizaciones sin items.');
        }

        $this->assertNotFalse(simplexml_load_string($this->xmlDe($this->generar($vacia))));
    }

    /** Si falta la plantilla, el error lo dice con claridad. */
    public function test_sin_plantilla_avisa_con_claridad(): void
    {
        $generador = new GeneradorDeOferta(
            CalcularCotizacion::crear(),
            storage_path('app/plantillas/no-existe.docx'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se encuentra la plantilla');

        $generador->generar(Cotizacion::findOrFail(55));
    }

    /**
     * El generador no pide mas marcadores de los que la plantilla real trae.
     *
     * La plantilla de la empresa NO se modifica: es el documento que ve el
     * cliente. Si alguien anade aqui un marcador nuevo, la plantilla real no
     * lo tendra y saldria impreso en crudo en la oferta enviada.
     *
     * Esta lista sale del sistema actual, que lleva anos usando esa misma
     * plantilla (NuevaCotizacion.php:2235-2246 y :2344).
     */
    public function test_no_exige_marcadores_que_la_plantilla_real_no_tiene(): void
    {
        $permitidos = [
            'fecha', 'codigo', 'cliente', 'contacto', 'cargo',
            'nombre', 'referencia', 'alcance', 'observaciones',
            /** La tabla de precios; pendiente de confirmar su nombre en la plantilla real. */
            'tabla',
        ];

        preg_match_all(
            "/set(?:Value|ComplexBlock)\(\s*'([a-zA-Z_0-9]+)'/",
            (string) file_get_contents(app_path('Dominio/Documentos/GeneradorDeOferta.php')),
            $coincidencias
        );

        $sobran = array_diff(array_unique($coincidencias[1]), $permitidos);

        $this->assertSame(
            [],
            array_values($sobran),
            'El generador rellena marcadores que la plantilla de la empresa no tiene: '
            . implode(', ', $sobran)
            . '. La plantilla no se modifica; hay que ajustar el codigo.'
        );
    }
}
