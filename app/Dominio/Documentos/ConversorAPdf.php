<?php

namespace App\Dominio\Documentos;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings as AjustesWord;
use RuntimeException;
use Throwable;

/**
 * Convierte el documento de oferta a PDF.
 *
 * El sistema actual abre Microsoft Word en el servidor
 * (new COM("Word.Application")) y le pide que exporte. Eso solo funciona en
 * Windows con Word instalado, y el hosting de destino es cPanel compartido:
 * sin Word, sin LibreOffice y sin permiso para instalar nada.
 *
 * Aqui la conversion es PHP puro, de modo que funciona en cualquier alojamiento.
 *
 * A cambio hay una perdida de fidelidad que conviene tener presente: DomPDF
 * no interpreta un .docx, sino el HTML al que PhpWord lo traduce, asi que el
 * PDF no sale identico al Word. Por eso el Word sigue siendo el documento
 * principal y el PDF una conveniencia para enviar por correo.
 */
final class ConversorAPdf
{
    public function __construct(
        private readonly GeneradorDeOferta $generador,
    ) {}

    public static function crear(): self
    {
        return new self(GeneradorDeOferta::crear());
    }

    /**
     * Genera el PDF de una cotizacion, pasando por el Word.
     *
     * @throws RuntimeException si la conversion falla
     */
    public function desdeCotizacion(\App\Models\Cotizacion $cotizacion, ?string $destino = null): string
    {
        $docx = $this->generador->generar($cotizacion);

        try {
            return $this->desdeDocx($docx, $destino);
        } finally {
            /** El .docx intermedio no hace falta una vez convertido. */
            if (is_file($docx)) {
                unlink($docx);
            }
        }
    }

    /**
     * Convierte un .docx existente.
     *
     * @throws RuntimeException si la conversion falla
     */
    public function desdeDocx(string $docx, ?string $destino = null): string
    {
        if (! is_file($docx)) {
            throw new RuntimeException("No se encuentra el documento a convertir: {$docx}");
        }

        $this->configurarMotor();

        $destino ??= preg_replace('/\.docx$/i', '.pdf', $docx);

        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }

        try {
            $documento = IOFactory::load($docx);

            IOFactory::createWriter($documento, 'PDF')->save($destino);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No se pudo convertir el documento a PDF: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if (! is_file($destino) || filesize($destino) === 0) {
            throw new RuntimeException('La conversion no produjo ningun PDF.');
        }

        return $destino;
    }

    /**
     * Apunta PhpWord al motor de PDF.
     *
     * Esta aislado aqui para que cambiar de motor sea tocar un solo sitio:
     * si algun dia el alojamiento permite LibreOffice, que da mucha mejor
     * fidelidad, solo cambia este metodo.
     */
    private function configurarMotor(): void
    {
        AjustesWord::setPdfRendererName(AjustesWord::PDF_RENDERER_DOMPDF);
        AjustesWord::setPdfRendererPath(base_path('vendor/dompdf/dompdf'));
    }
}
