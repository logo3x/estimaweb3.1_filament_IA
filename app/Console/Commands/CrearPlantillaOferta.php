<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Recrea la plantilla de oferta para desarrollo.
 *
 * ESTA NO ES LA PLANTILLA DE LA EMPRESA.
 *
 * La real (GG-ADM1-F-1 version 7) es un formato controlado de Confipetrol,
 * con su membrete, su logo, los sellos ISO del pie y la firma del
 * representante legal. **No se modifica**: es el documento que se envia a
 * los clientes. Vive en el servidor, en storage/app/public/template.docx,
 * y no esta en el repositorio.
 *
 * Esta reproduce su ESTRUCTURA -el texto fijo, el orden de los marcadores y
 * las dos paginas- para que las pruebas se parezcan al documento real. Lo
 * que no reproduce es la imagen de marca, que no hace falta para probar.
 *
 * Al desplegar se sustituye el archivo por el real. Si algun marcador no
 * coincidiera, **se corrige el codigo, nunca el .docx**.
 */
class CrearPlantillaOferta extends Command
{
    protected $signature = 'plantilla:oferta {--forzar : Sobrescribir si ya existe}';

    protected $description = 'Recrea la plantilla .docx de oferta para desarrollo';

    /** Tipografia del documento real. */
    private const FUENTE = ['name' => 'Arial', 'size' => 10];

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

        $this->info("Plantilla de desarrollo creada: {$ruta}");
        $this->warn('No es la plantilla de la empresa: sustitúyala por la real al desplegar.');

        return self::SUCCESS;
    }

    private function dibujar(): PhpWord
    {
        $documento = new PhpWord;
        $documento->setDefaultFontName('Arial');
        $documento->setDefaultFontSize(10);

        $this->paginaDeLaOferta($documento);
        $this->paginaDeLaFirma($documento);

        return $documento;
    }

    /**
     * Primera pagina: destinatario, alcance, precios y observaciones.
     *
     * El orden de los marcadores es el del documento real. Dos de ellos van
     * cruzados respecto a lo que sugiere su nombre, y asi debe quedarse:
     * la etiqueta 'Referencia:' lleva ${nombre} (el servicio) y la etiqueta
     * 'Asunto:' lleva ${referencia}.
     */
    private function paginaDeLaOferta(PhpWord $documento): void
    {
        $seccion = $documento->addSection();

        $this->encabezado($seccion, 1);

        $seccion->addText('Bogotá, ${fecha}', self::FUENTE, ['alignment' => Jc::RIGHT, 'spaceAfter' => 360]);
        $seccion->addText('${codigo}', self::FUENTE, ['spaceAfter' => 360]);

        $seccion->addText('Señores', self::FUENTE);
        $seccion->addText('${cliente}', self::FUENTE);
        $seccion->addText('Bogotá-Colombia', self::FUENTE, ['spaceAfter' => 480]);

        /** 'Atn.' y 'Referencia:' en negrita, como en el formato real. */
        $this->campo($seccion, 'Atn.', '${contacto}', valorEnNegrita: true);
        $seccion->addText('${cargo}', ['name' => 'Arial', 'size' => 10, 'bold' => true], ['spaceAfter' => 120]);
        $this->campo($seccion, 'Referencia:', '${nombre}');
        $this->campo($seccion, 'Asunto:', '${referencia}', valorEnNegrita: true);

        $seccion->addTextBreak(2);

        $seccion->addText('Estimados señores,', self::FUENTE, ['spaceAfter' => 120]);
        $seccion->addText(
            'Por medio de la presente, hacemos entrega de la cotización del asunto.',
            self::FUENTE,
            ['spaceAfter' => 240]
        );

        $this->titulo($seccion, 'ALCANCE');
        $seccion->addText('${alcance}', self::FUENTE, ['spaceAfter' => 360]);

        $this->titulo($seccion, 'OFERTA ECONÓMICA');

        /** Aqui el generador inserta la tabla de precios. */
        $seccion->addText('${tabla}', self::FUENTE, ['spaceAfter' => 360]);

        $this->titulo($seccion, 'OBSERVACIONES.');

        $this->vineta($seccion, 'VIGENCIA.', ' Esta propuesta tiene vigencia de 15 días calendario, a partir del día de entrega.');
        $this->vineta($seccion, 'FORMA DE PAGO.', ' 30 días después de radicada la factura');

        /** Las observaciones de la cotizacion van como una vineta mas. */
        $seccion->addListItem('${observaciones}', 0, self::FUENTE);

        $this->pie($seccion);
    }

    /** Segunda pagina: despedida y firma. */
    private function paginaDeLaFirma(PhpWord $documento): void
    {
        $seccion = $documento->addSection();

        $this->encabezado($seccion, 2);

        $seccion->addText(
            'Esperamos que nuestra oferta sea de su mayor conveniencia y quedamos atentos a cualquier inquietud.',
            self::FUENTE,
            ['alignment' => Jc::BOTH, 'spaceAfter' => 240]
        );

        $seccion->addText('Atentamente,', self::FUENTE);
        $seccion->addTextBreak(4);

        $seccion->addText('Oscar Jeovanny Fernández Moreno', ['name' => 'Arial', 'size' => 10, 'bold' => true]);
        $seccion->addText('Presidente y Representante Legal', self::FUENTE);
        $seccion->addText('presidencia@confipetrol.com', self::FUENTE);
        $seccion->addText('Confipetrol S.A.S', self::FUENTE);
        $seccion->addText('Cra. 15 # 98-26 Of 401 Barrio El Chicó, Bogotá D.C - Colombia', self::FUENTE);

        $this->pie($seccion);
    }

    /**
     * Encabezado con los datos del formato controlado.
     *
     * En la plantilla real lleva ademas el logo de Confipetrol.
     */
    private function encabezado(\PhpOffice\PhpWord\Element\Section $seccion, int $pagina): void
    {
        $encabezado = $seccion->addHeader();
        $gris = ['name' => 'Arial', 'size' => 8, 'color' => '595959'];

        $encabezado->addText('CONFIPETROL', ['name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => '1F4E79']);

        foreach ([
            'Código: GG-ADM1-F-1',
            'Versión: 7',
            'Fecha: 30/07/2024',
            "Página {$pagina} de 2",
        ] as $linea) {
            $encabezado->addText($linea, $gris, ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);
        }
    }

    /** Pie con las sedes y las certificaciones. */
    private function pie(\PhpOffice\PhpWord\Element\Section $seccion): void
    {
        $pie = $seccion->addFooter();
        $azul = ['name' => 'Arial', 'size' => 7, 'color' => '1F4E79'];

        $pie->addText('ISO 9001  ·  ISO 14001  ·  ISO 45001  ·  ISO 50001  ·  IQNET', ['name' => 'Arial', 'size' => 7, 'bold' => true]);
        $pie->addText('SC 5779    SA 315    OS 215    GE-CER854891', ['name' => 'Arial', 'size' => 7]);

        foreach ([
            'Bogotá D.C – Colombia, +57 +60 (1) 4232949',
            'Lima – Perú, +51 (1) 710610',
            'Santiago de Chile – Chile, (+56 9) 5049 7979',
            'Santa Cruz de La Sierra – Bolivia, +59 (1) 3445111',
            'Quito – Ecuador, (+593) 22903415',
            'Caracas Miranda – Venezuela',
        ] as $sede) {
            $pie->addText($sede, $azul, ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);
        }

        $pie->addText('www.confipetrol.com', ['name' => 'Arial', 'size' => 7, 'color' => 'ED7D31'], ['alignment' => Jc::RIGHT]);
    }

    /** Una linea de 'etiqueta: valor', como las de la cabecera de la carta. */
    private function campo(
        \PhpOffice\PhpWord\Element\Section $seccion,
        string $etiqueta,
        string $valor,
        bool $valorEnNegrita = false,
    ): void {
        $parrafo = $seccion->addTextRun(['spaceAfter' => 120]);

        $parrafo->addText($etiqueta . "\t", ['name' => 'Arial', 'size' => 10, 'bold' => true]);
        $parrafo->addText($valor, ['name' => 'Arial', 'size' => 10, 'bold' => $valorEnNegrita]);
    }

    /** Un titulo de seccion: negrita y subrayado. */
    private function titulo(\PhpOffice\PhpWord\Element\Section $seccion, string $texto): void
    {
        $seccion->addText(
            $texto,
            ['name' => 'Arial', 'size' => 10, 'bold' => true, 'underline' => 'single'],
            ['spaceAfter' => 180]
        );
    }

    /** Una vineta con su titulo en negrita. */
    private function vineta(\PhpOffice\PhpWord\Element\Section $seccion, string $titulo, string $texto): void
    {
        $parrafo = $seccion->addListItemRun(0);

        $parrafo->addText($titulo, ['name' => 'Arial', 'size' => 10, 'bold' => true]);
        $parrafo->addText($texto, self::FUENTE);
    }
}
