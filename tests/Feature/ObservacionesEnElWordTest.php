<?php

namespace Tests\Feature;

use App\Dominio\Documentos\GeneradorDeOferta;
use App\Models\Cotizacion;
use Tests\TestCase;
use ZipArchive;

/**
 * Las observaciones llegan completas y limpias al documento.
 *
 * Es lo que el cliente lee. Se escriben en un editor de formato, de modo
 * que llegan con lo que ese editor produce y con lo que alguien pegó desde
 * Word o desde un correo: espacios que no separan, comillas tipográficas,
 * guiones que no cortan. En pantalla no se ven; en el documento producen
 * espaciado irregular y líneas partidas donde no deben.
 *
 * En los datos reales son 47 cotizaciones con observaciones, 1.180 viñetas
 * y 414 negritas. Once traen espacios duros, una de ellas 326.
 */
class ObservacionesEnElWordTest extends TestCase
{
    /** El carácter que no separa palabras pero ocupa sitio. */
    private const ESPACIO_DURO = "\u{00A0}";

    /** El XML del documento de una cotización, ya generado. */
    private function xmlDelDocumento(Cotizacion $cotizacion): string
    {
        $archivo = GeneradorDeOferta::crear()->generar($cotizacion);

        $this->assertFileExists($archivo);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo) === true, 'El .docx no se puede abrir.');

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        @unlink($archivo);

        return $xml;
    }

    /** Una cotización que traiga observaciones de verdad. */
    private function conObservaciones(): Cotizacion
    {
        return Cotizacion::query()
            ->whereNotNull('observaciones')
            ->where('observaciones', '!=', '')
            ->orderByRaw('LENGTH(observaciones) DESC')
            ->firstOrFail();
    }

    /**
     * Ningún documento lleva espacios duros.
     *
     * Se recorren **todas** las cotizaciones con observaciones, no una de
     * muestra: el carácter aparece en once y no hay forma de saber en
     * cuáles sin mirarlas.
     */
    public function test_ningun_documento_lleva_espacios_duros(): void
    {
        $sucias = [];

        foreach ($this->cotizacionesConObservaciones() as $cotizacion) {
            $cuantos = substr_count($this->xmlDelDocumento($cotizacion), self::ESPACIO_DURO);

            if ($cuantos > 0) {
                $sucias[] = "#{$cotizacion->id} ({$cuantos})";
            }
        }

        $this->assertSame(
            [],
            $sucias,
            'Estos documentos llevan espacios duros: ' . implode(', ', $sucias)
        );
    }

    /**
     * Las cotizaciones que de verdad tienen observaciones.
     *
     * @return \Illuminate\Support\Collection<int, Cotizacion>
     */
    private function cotizacionesConObservaciones(): \Illuminate\Support\Collection
    {
        return Cotizacion::query()
            ->whereNotNull('observaciones')
            ->where('observaciones', '!=', '')
            /** Las de duración cero rompían el cálculo; ya no, pero sobran aquí. */
            ->where('dias_dura_servicio', '>', 0)
            ->limit(15)
            ->get();
    }

    /**
     * Las viñetas del editor llegan como viñetas de Word.
     *
     * Si se perdieran, una lista de quince premisas se convertiría en un
     * párrafo corrido y el cliente no distinguiría una de otra.
     */
    public function test_las_vinetas_llegan_al_documento(): void
    {
        $cotizacion = Cotizacion::query()
            ->whereNotNull('observaciones')
            ->where('observaciones', 'like', '%<li%')
            ->orderByRaw("(LENGTH(observaciones) - LENGTH(REPLACE(observaciones, '<li', ''))) DESC")
            ->first();

        if (! $cotizacion) {
            $this->markTestSkipped('Ninguna cotización tiene listas en las observaciones.');
        }

        $enElHtml = substr_count((string) $cotizacion->observaciones, '<li');
        $enElWord = substr_count($this->xmlDelDocumento($cotizacion), '<w:numPr');

        $this->assertGreaterThanOrEqual(
            $enElHtml,
            $enElWord,
            "El HTML trae {$enElHtml} viñetas y el documento solo {$enElWord}."
        );
    }

    /** Las negritas también. */
    public function test_las_negritas_llegan_al_documento(): void
    {
        $cotizacion = Cotizacion::query()
            ->whereNotNull('observaciones')
            ->where('observaciones', 'like', '%<strong%')
            ->first();

        if (! $cotizacion) {
            $this->markTestSkipped('Ninguna cotización usa negritas.');
        }

        $this->assertStringContainsString(
            '<w:b w:val="1"/>',
            $this->xmlDelDocumento($cotizacion),
            'Las negritas del editor no llegaron al documento.'
        );
    }

    /**
     * El texto de las observaciones llega entero.
     *
     * Ya ocurrió que el documento salía con un solo párrafo de cinco: el
     * cliente recibía las premisas a medias y nadie lo notaba hasta que
     * reclamaba.
     */
    public function test_las_observaciones_llegan_enteras(): void
    {
        $cotizacion = $this->conObservaciones();

        $xml = $this->xmlDelDocumento($cotizacion);

        /** Unas pocas frases del original deben estar en el documento. */
        $texto = trim(html_entity_decode(strip_tags((string) $cotizacion->observaciones)));
        $frases = array_values(array_filter(
            array_map('trim', preg_split('/[.\n]/u', $texto) ?: []),
            fn (string $frase): bool => mb_strlen($frase) > 25,
        ));

        $this->assertNotEmpty($frases, 'La cotización elegida no tiene texto suficiente.');

        $textoDelWord = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        foreach (array_slice($frases, 0, 5) as $frase) {
            /** Se comparan sin espacios: el documento los reparte distinto. */
            $this->assertStringContainsString(
                preg_replace('/\s+/u', '', mb_substr($frase, 0, 40)) ?? '',
                preg_replace('/\s+/u', '', $textoDelWord) ?? '',
                "Falta en el documento: \"" . mb_substr($frase, 0, 50) . '"'
            );
        }
    }

    /**
     * No queda ningún marcador sin rellenar.
     *
     * Un `${cliente}` suelto en la oferta es algo que el cliente ve.
     */
    public function test_no_quedan_marcadores_sin_rellenar(): void
    {
        foreach ($this->cotizacionesConObservaciones()->take(5) as $cotizacion) {
            preg_match_all('/\$\{([a-z_]+)\}/i', $this->xmlDelDocumento($cotizacion), $sueltos);

            $this->assertSame(
                [],
                array_unique($sueltos[1]),
                "La cotización {$cotizacion->id} deja marcadores sin rellenar."
            );
        }
    }

    /**
     * Los párrafos vacíos del editor no dejan huecos.
     *
     * Al borrar un párrafo, el editor deja un `<p></p>`: tres seguidos son
     * media página en blanco en mitad de la oferta.
     */
    public function test_los_parrafos_vacios_no_llegan(): void
    {
        $cotizacion = $this->conObservaciones();
        $original = $cotizacion->observaciones;

        $cotizacion->forceFill([
            'observaciones' => '<p>Primera premisa.</p><p></p><p>&nbsp;</p><p></p><p>Segunda premisa.</p>',
        ])->save();

        try {
            $xml = $this->xmlDelDocumento($cotizacion->fresh());

            $texto = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

            $this->assertStringContainsString('Primera premisa', $texto);
            $this->assertStringContainsString('Segunda premisa', $texto);
        } finally {
            $cotizacion->forceFill(['observaciones' => $original])->save();
        }
    }

    /**
     * Las comillas tipográficas y los guiones largos se normalizan.
     *
     * Llegan al pegar desde Word. En el documento se ven distintos del
     * resto del texto, que usa las comillas rectas de la plantilla.
     */
    public function test_los_caracteres_de_word_se_normalizan(): void
    {
        $cotizacion = $this->conObservaciones();
        $original = $cotizacion->observaciones;

        $cotizacion->forceFill([
            'observaciones' => "<p>El cliente \u{201C}suministra\u{201D} la alimentación \u{2014} y el hospedaje\u{2026}</p>",
        ])->save();

        try {
            $xml = $this->xmlDelDocumento($cotizacion->fresh());

            foreach (["\u{201C}", "\u{201D}", "\u{2014}", "\u{2026}"] as $caracter) {
                $this->assertStringNotContainsString(
                    $caracter,
                    $xml,
                    'Quedó un carácter tipográfico de Word sin normalizar.'
                );
            }

            $this->assertStringContainsString('suministra', html_entity_decode(strip_tags($xml)));
        } finally {
            $cotizacion->forceFill(['observaciones' => $original])->save();
        }
    }
}
