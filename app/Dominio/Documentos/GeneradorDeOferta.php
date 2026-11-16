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

        $this->exigirQueLaTablaEntrara($plantilla);

        $destino ??= $this->rutaDestino($cotizacion);

        $carpeta = dirname($destino);

        if (! is_dir($carpeta) && ! mkdir($carpeta, 0775, true) && ! is_dir($carpeta)) {
            throw new RuntimeException(
                "No se pudo crear la carpeta de los documentos: {$carpeta}. "
                . 'Revise los permisos de storage/app.'
            );
        }

        $plantilla->saveAs($destino);

        return $destino;
    }

    /**
     * Comprueba que la tabla de precios entro de verdad.
     *
     * `setComplexBlock` **no avisa si el marcador no esta**: busca
     * `${tabla}` en el documento y, si no lo encuentra, se va sin hacer
     * nada. El resultado seria una oferta bien formada, descargable y
     * **sin precios**, que es justo lo que no puede llegarle a un cliente.
     *
     * Importa ahora porque la plantilla real de la empresa todavia no esta
     * en el servidor: se sube a mano al desplegar, y basta con que su
     * marcador se llame de otro modo.
     *
     * Mejor fallar aqui, con un mensaje que dice que revisar, que mandar
     * una oferta incompleta.
     *
     * @throws RuntimeException si el marcador sigue sin sustituir
     */
    private function exigirQueLaTablaEntrara(TemplateProcessor $plantilla): void
    {
        if (! in_array('tabla', $plantilla->getVariables(), true)) {
            return;
        }

        throw new RuntimeException(
            'La plantilla no tiene el marcador ${tabla}, así que el documento '
            . 'saldría sin la tabla de precios. Revise que la plantilla en '
            . 'storage/app/plantillas sea la correcta.'
        );
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
            $plantilla->setValue($marcador, $this->limpiarTexto((string) $valor) ?: 'N/A');
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
     * Quita del texto los caracteres que la web deja y Word no entiende.
     *
     * Se aplica a TODO lo que entra al documento -datos de cabecera,
     * nombres de los items, observaciones-, no solo a las observaciones:
     * los espacios duros tambien aparecen en los nombres de los items, que
     * se escriben copiando de un correo o de otra cotizacion.
     *
     * En pantalla no se ven; en el documento producen espaciado irregular
     * y lineas que parten donde no deben.
     */
    private function limpiarTexto(string $texto): string
    {
        $limpio = strtr($texto, self::REEMPLAZOS_DEL_EDITOR);

        /** Espacios repetidos: en HTML cuentan como uno, en Word no. */
        $limpio = preg_replace('/[ \t]{2,}/u', ' ', $limpio) ?? $limpio;

        return trim($limpio);
    }

    /**
     * Prepara el HTML del editor para PhpWord.
     *
     * Las observaciones se escriben en un editor de formato, asi que llegan
     * con lo que ese editor produce y con lo que el usuario pego desde Word
     * o desde un correo. Eso trae caracteres que en pantalla no se ven pero
     * en el documento si: espacios que no separan, guiones que no cortan,
     * comillas tipograficas.
     *
     * De los datos reales: once cotizaciones traen **espacios duros**, una
     * de ellas trescientos veintiseis. Pasaban al Word tal cual y producian
     * un espaciado irregular en el documento que ve el cliente.
     */
    private function limpiarHtml(string $html): string
    {
        $limpio = strtr($html, self::REEMPLAZOS_DEL_EDITOR);

        /**
         * Las lineas en blanco que el editor deja al borrar un parrafo.
         *
         * Un `<p>` vacio es un renglon en blanco en el documento: tres
         * seguidos dejan media pagina muerta en mitad de la oferta.
         */
        $limpio = preg_replace('#<p>(\s|&nbsp;)*</p>#iu', '', $limpio) ?? $limpio;

        /** Espacios repetidos: en HTML cuentan como uno, en Word no. */
        $limpio = preg_replace('/[ \t]{2,}/u', ' ', $limpio) ?? $limpio;

        /** Sin elemento raiz, el analizador no interpreta el fragmento. */
        return '<div>' . trim($limpio) . '</div>';
    }

    /**
     * Caracteres de la web que no deben llegar al documento.
     *
     * No es una lista arbitraria: cada uno se vio en los datos reales o lo
     * produce el editor al pegar desde otro programa.
     *
     * @var array<string, string>
     */
    private const REEMPLAZOS_DEL_EDITOR = [
        /** La entidad y su forma ya decodificada: llegan de las dos maneras. */
        '&nbsp;' => ' ',
        "\u{00A0}" => ' ',
        /** Espacios de ancho raro que algunos editores insertan. */
        "\u{2007}" => ' ',
        "\u{202F}" => ' ',
        "\u{200B}" => '',
        /** Marca de orden de bytes, invisible y heredada de copiar y pegar. */
        "\u{FEFF}" => '',
        /** Guion que no corta: Word lo respeta y parte mal la linea. */
        "\u{2011}" => '-',
        /** Comillas y guiones tipograficos de Word. */
        "\u{2018}" => "'",
        "\u{2019}" => "'",
        "\u{201C}" => '"',
        "\u{201D}" => '"',
        "\u{2013}" => '-',
        "\u{2014}" => '-',
        /** Puntos suspensivos en un solo caracter. */
        "\u{2026}" => '...',
        /** Saltos de linea y parrafo de Unicode, que Word no entiende. */
        "\u{2028}" => ' ',
        "\u{2029}" => ' ',
    ];

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
        /** Tambien se limpian: los nombres se escriben copiando de otro sitio. */
        $tabla->addCell(2000, $fondo)->addText(
            $this->limpiarTexto((string) $item->nombre),
            $fuente,
            ['alignment' => Jc::LEFT],
        );
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
