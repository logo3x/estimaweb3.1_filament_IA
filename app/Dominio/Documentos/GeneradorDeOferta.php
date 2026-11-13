<?php

namespace App\Dominio\Documentos;

use App\Dominio\CalcularCotizacion;
use App\Dominio\CotizacionCalculada;
use App\Models\Cotizacion;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Genera el documento de oferta en Word.
 *
 * Parte de una plantilla .docx con el membrete y las condiciones de la
 * empresa, y rellena nueve marcadores de texto mas la tabla de precios.
 *
 * LA PLANTILLA NO SE MODIFICA. Es el documento que ve el cliente, ya existe
 * y se mantiene tal cual. Si un marcador no coincide, se ajusta este codigo
 * al nombre que traiga la plantilla, nunca al reves. Los nueve marcadores de
 * texto coinciden con los del sistema actual
 * (NuevaCotizacion.php:2235-2246,2344); el de la tabla esta pendiente de
 * verificar contra la plantilla real.
 *
 * A diferencia del sistema actual, NO depende de ningun componente de
 * interfaz: alli la generacion vive dentro de un componente Livewire de
 * 4.122 lineas, y la pantalla nueva tiene que instanciarlo a mano y
 * asignarle diez propiedades publicas para pedirle el documento.
 *
 * Los importes salen del dominio, no de las columnas guardadas: muchas
 * cotizaciones arrastran totales de versiones anteriores del calculo.
 */
final class GeneradorDeOferta
{
    /** Colores de la tabla, tomados del documento actual. */
    private const AZUL_CABECERA = '5081BB';

    private const AZUL_FILA = 'D9E2F3';

    private const FUENTE = 'Arial';

    public function __construct(
        private readonly CalcularCotizacion $calculadora,
        private readonly string $rutaPlantilla,
    ) {}

    public static function crear(?string $rutaPlantilla = null): self
    {
        return new self(
            CalcularCotizacion::crear(),
            $rutaPlantilla ?? storage_path('app/plantillas/oferta.docx'),
        );
    }

    /**
     * Escribe el documento y devuelve su ruta.
     *
     * @throws RuntimeException si falta la plantilla
     */
    public function generar(Cotizacion $cotizacion, ?string $destino = null): string
    {
        if (! is_file($this->rutaPlantilla)) {
            throw new RuntimeException(
                "No se encuentra la plantilla del documento: {$this->rutaPlantilla}"
            );
        }

        $calculo = $this->calculadora->para($cotizacion);

        $plantilla = new TemplateProcessor($this->rutaPlantilla);

        $this->rellenarDatos($plantilla, $cotizacion);
        $this->rellenarObservaciones($plantilla, $cotizacion);

        $plantilla->setComplexBlock('tabla', $this->construirTabla($cotizacion, $calculo));

        $destino ??= $this->rutaDestino($cotizacion);

        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }

        $plantilla->saveAs($destino);

        return $destino;
    }

    private function rellenarDatos(TemplateProcessor $plantilla, Cotizacion $cotizacion): void
    {
        $plantilla->setValue('fecha', now()->translatedFormat('d \d\e F \d\e Y'));

        foreach ([
            'codigo' => $cotizacion->codigo,
            'cliente' => $cotizacion->cliente,
            'contacto' => $cotizacion->contacto,
            'cargo' => $cotizacion->cargo,
            'nombre' => $cotizacion->nombre,
            'referencia' => $cotizacion->referencia,
            'alcance' => $cotizacion->alcance,
        ] as $marcador => $valor) {
            $plantilla->setValue($marcador, (string) ($valor ?: 'N/A'));
        }
    }

    /**
     * Inserta las observaciones conservando su formato.
     *
     * Vienen en HTML del editor de texto enriquecido. Se verifico sobre los
     * datos reales: las 47 cotizaciones con observaciones usan ese formato.
     *
     * No se usa setComplexBlock porque sustituye el marcador por UN solo
     * elemento: unas observaciones con dos titulos y una lista producen
     * cinco, de los que se colocaria el primero y se perderian cuatro,
     * ademas de quedar el titulo pegado al parrafo siguiente. Aqui se
     * escriben todos.
     *
     * Si el HTML resulta invalido, se cae a texto plano antes que dejar el
     * documento sin generar.
     */
    private function rellenarObservaciones(TemplateProcessor $plantilla, Cotizacion $cotizacion): void
    {
        $observaciones = trim((string) $cotizacion->observaciones);

        if ($observaciones === '') {
            $plantilla->setValue('observaciones', '');

            return;
        }

        try {
            $seccion = (new \PhpOffice\PhpWord\PhpWord)->addSection();

            Html::addHtml($seccion, $this->limpiarHtml($observaciones), false, false);

            $xml = $this->xmlDeLosBloques($seccion->getElements());

            if ($xml === '') {
                $plantilla->setValue('observaciones', strip_tags($observaciones));

                return;
            }

            $plantilla->replaceXmlBlock('observaciones', $xml, 'w:p');
        } catch (\Throwable) {
            $plantilla->setValue('observaciones', strip_tags($observaciones));
        }
    }

    /**
     * El XML de Word de una lista de elementos, uno tras otro.
     *
     * Es lo que hace setComplexBlock internamente, pero para todos los
     * elementos en vez de para uno.
     *
     * @param array<\PhpOffice\PhpWord\Element\AbstractElement> $elementos
     */
    private function xmlDeLosBloques(array $elementos): string
    {
        $xml = '';

        foreach ($elementos as $elemento) {
            $nombre = (new \ReflectionClass($elemento))->getShortName();
            $escritor = 'PhpOffice\\PhpWord\\Writer\\Word2007\\Element\\' . $nombre;

            if (! class_exists($escritor)) {
                continue;
            }

            $destino = new \PhpOffice\PhpWord\Shared\XMLWriter;

            (new $escritor($destino, $elemento, false))->write();

            $xml .= $destino->getData();
        }

        return $xml;
    }

    /**
     * Prepara el HTML para PhpWord.
     *
     * Las entidades sin escapar rompen el analisis, y un fragmento suelto
     * sin elemento raiz tampoco se interpreta bien.
     */
    private function limpiarHtml(string $html): string
    {
        $limpio = str_replace('&nbsp;', ' ', $html);

        return '<div>' . $limpio . '</div>';
    }

    /**
     * La tabla de precios que ve el cliente.
     *
     * Con el AIU desglosado se anaden sus tres lineas y el subtotal; con el
     * AIU incorporado los items ya lo traen dentro y esas filas sobran.
     */
    private function construirTabla(Cotizacion $cotizacion, CotizacionCalculada $calculo): Table
    {
        $tabla = new Table([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 80,
        ]);

        $this->agregarCabecera($tabla);

        $numero = 0;

        foreach ($cotizacion->items()->orderBy('numero')->get() as $item) {
            $unitario = $calculo->valorUnitarioPorItem[$item->id] ?? 0.0;

            $this->agregarFilaDeItem($tabla, ++$numero, $item, $unitario);
        }

        $this->agregarTotales($tabla, $calculo);

        return $tabla;
    }

    private function agregarCabecera(Table $tabla): void
    {
        $tabla->addRow(200);

        foreach ([
            ['ITEM', 1000],
            ['DESCRIPCIÓN', 2000],
            ['UNIDAD', 1000],
            ['CANTIDAD', 1000],
            ['VALOR UNITARIO', 1500],
            ['VALOR PARCIAL', 1500],
        ] as [$titulo, $ancho]) {
            $tabla->addCell($ancho, ['bgColor' => self::AZUL_CABECERA, 'valign' => 'center'])
                ->addText(
                    $titulo,
                    ['bold' => true, 'color' => 'FFFFFF', 'name' => self::FUENTE],
                    ['alignment' => Jc::CENTER]
                );
        }
    }

    private function agregarFilaDeItem(Table $tabla, int $numero, object $item, float $unitario): void
    {
        $fondo = ['bgColor' => self::AZUL_FILA, 'valign' => 'center'];
        $fuente = ['name' => self::FUENTE];

        $tabla->addRow();
        $tabla->addCell(1000, $fondo)->addText((string) $numero, $fuente, ['alignment' => Jc::CENTER]);
        $tabla->addCell(2000, $fondo)->addText((string) $item->nombre, $fuente, ['alignment' => Jc::LEFT]);
        $tabla->addCell(1000, $fondo)->addText((string) $item->unidad, $fuente, ['alignment' => Jc::LEFT]);
        $tabla->addCell(1000, $fondo)->addText((string) $item->cantidad, $fuente, ['alignment' => Jc::RIGHT]);
        $tabla->addCell(1500, $fondo)->addText($this->pesos($unitario), $fuente, ['alignment' => Jc::RIGHT]);
        $tabla->addCell(1500, $fondo)->addText(
            $this->pesos($unitario * (int) $item->cantidad),
            $fuente,
            ['alignment' => Jc::RIGHT]
        );
    }

    private function agregarTotales(Table $tabla, CotizacionCalculada $calculo): void
    {
        $totales = $calculo->totales;

        /**
         * Con el AIU incorporado, los items ya lo traen dentro: el total que
         * cierra la tabla es el subtotal. Con el AIU desglosado, los items
         * muestran el costo directo y A+I+U van en lineas aparte.
         */
        $muestraDesglose = $calculo->parametros->muestraAiuDesglosado;

        $this->agregarTotal(
            $tabla,
            'VALOR TOTAL',
            $muestraDesglose ? $totales->costoDirecto : $totales->subtotal
        );

        if ($muestraDesglose) {
            $this->agregarPorcentaje($tabla, 'Administración', $calculo->parametros->administracion, $totales->administracion);
            $this->agregarPorcentaje($tabla, 'Imprevistos', $calculo->parametros->imprevistos, $totales->imprevistos);
            $this->agregarPorcentaje($tabla, 'Utilidad', $calculo->parametros->utilidad, $totales->utilidad);
            $this->agregarTotal($tabla, 'VALOR TOTAL CON AIU', $totales->subtotal);
        }

        $this->agregarPorcentaje($tabla, 'IVA', 19.0, $totales->iva);
        $this->agregarTotal($tabla, 'COSTO TOTAL INCLUIDO IVA', $totales->total);
    }

    private function agregarTotal(Table $tabla, string $titulo, float $monto): void
    {
        $negrita = ['bold' => true, 'name' => self::FUENTE];

        $tabla->addRow(200, ['bgColor' => self::AZUL_FILA]);
        $tabla->addCell(6500, ['gridSpan' => 5, 'valign' => 'center'])
            ->addText($titulo, $negrita, ['alignment' => Jc::RIGHT]);
        $tabla->addCell(1500, ['valign' => 'center'])
            ->addText($this->pesos($monto), $negrita, ['alignment' => Jc::RIGHT]);
    }

    private function agregarPorcentaje(Table $tabla, string $titulo, float $porcentaje, float $monto): void
    {
        $negrita = ['bold' => true, 'name' => self::FUENTE];

        $tabla->addRow(200, ['bgColor' => self::AZUL_FILA]);
        $tabla->addCell(5000, ['gridSpan' => 4, 'valign' => 'center'])
            ->addText($titulo, $negrita, ['alignment' => Jc::RIGHT]);
        $tabla->addCell(1000, ['valign' => 'center'])
            ->addText(number_format($porcentaje, 2, ',', '.') . '%', $negrita, ['alignment' => Jc::RIGHT]);
        $tabla->addCell(1000, ['valign' => 'center'])
            ->addText($this->pesos($monto), $negrita, ['alignment' => Jc::RIGHT]);
    }

    private function pesos(float $monto): string
    {
        return '$' . number_format($monto, 2, ',', '.');
    }

    /** Nombre del archivo: identifica la cotizacion sin tener que abrirlo. */
    private function rutaDestino(Cotizacion $cotizacion): string
    {
        $cliente = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $cotizacion->cliente) ?: 'cliente';

        return storage_path(sprintf(
            'app/ofertas/%d-%s-%s.docx',
            $cotizacion->id,
            trim(mb_strimwidth($cliente, 0, 40, ''), '-'),
            now()->format('Ymd-His')
        ));
    }
}
