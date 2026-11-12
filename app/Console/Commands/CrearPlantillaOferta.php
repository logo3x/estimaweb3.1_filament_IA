<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Crea una plantilla de oferta con los marcadores que espera el generador.
 *
 * La plantilla real lleva el membrete y las condiciones de la empresa, y
 * vive en el servidor de produccion. Esta es un sustituto para desarrollo y
 * pruebas: tiene los mismos marcadores, de modo que el generador se puede
 * ejercitar sin esperar a que la definitiva este disponible.
 *
 * Al pasar a produccion basta con sustituir el archivo por el real; el
 * contrato son los marcadores, no el diseno.
 */
class CrearPlantillaOferta extends Command
{
    protected $signature = 'plantilla:oferta {--forzar : Sobrescribir si ya existe}';

    protected $description = 'Crea una plantilla .docx de oferta para desarrollo';

    /**
     * Marcadores que el generador rellena.
     *
     * @var array<string, string>
     */
    private const MARCADORES = [
        'fecha' => 'Fecha',
        'codigo' => 'Código',
        'cliente' => 'Cliente',
        'contacto' => 'Contacto',
        'cargo' => 'Cargo',
        'referencia' => 'Referencia',
    ];

    public function handle(): int
    {
        $ruta = storage_path('app/plantillas/oferta.docx');

        if (is_file($ruta) && ! $this->option('forzar')) {
            $this->warn("Ya existe: {$ruta}");
            $this->line('Use --forzar para sobrescribirla.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        $this->dibujar()->save($ruta, 'Word2007');

        $this->info("Plantilla creada: {$ruta}");
        $this->line('Sustitúyala por la plantilla real de la empresa conservando los marcadores.');

        return self::SUCCESS;
    }

    private function dibujar(): PhpWord
    {
        $documento = new PhpWord;
        $seccion = $documento->addSection();

        $seccion->addText(
            'PROPUESTA ECONÓMICA',
            ['bold' => true, 'size' => 16, 'name' => 'Arial'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );

        foreach (self::MARCADORES as $marcador => $etiqueta) {
            $seccion->addText(
                $etiqueta . ': ${' . $marcador . '}',
                ['name' => 'Arial', 'size' => 10],
                ['spaceAfter' => 60]
            );
        }

        $seccion->addTextBreak();

        $seccion->addText('SERVICIO', ['bold' => true, 'name' => 'Arial'], ['spaceAfter' => 60]);
        $seccion->addText('${nombre}', ['name' => 'Arial'], ['spaceAfter' => 120]);

        $seccion->addText('ALCANCE', ['bold' => true, 'name' => 'Arial'], ['spaceAfter' => 60]);
        $seccion->addText('${alcance}', ['name' => 'Arial'], ['spaceAfter' => 240]);

        $seccion->addText('OFERTA ECONÓMICA', ['bold' => true, 'name' => 'Arial'], ['spaceAfter' => 120]);

        /** Aqui el generador inserta la tabla de precios. */
        $seccion->addText('${tabla}');

        $seccion->addTextBreak();

        $seccion->addText('OBSERVACIONES', ['bold' => true, 'name' => 'Arial'], ['spaceAfter' => 60]);
        $seccion->addText('${observaciones}');

        return $documento;
    }
}
