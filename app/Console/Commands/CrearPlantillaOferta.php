<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Crea una plantilla de oferta con los marcadores que espera el generador.
 *
 * ESTA PLANTILLA ES DE PRUEBA. No es la de la empresa.
 *
 * La real lleva el membrete y las condiciones comerciales, ya existe y
 * **no se modifica**: es el documento que se envia a los clientes. Vive en el
 * servidor, en storage/app/public/template.docx, y no esta en el repositorio.
 *
 * Esta es un sustituto para desarrollo: tiene los mismos marcadores, de modo
 * que el generador se puede ejercitar sin disponer de la definitiva.
 *
 * Al desplegar se sustituye el archivo por el real. Si algun marcador no
 * coincide, **se corrige el codigo, nunca el .docx**.
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
