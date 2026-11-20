<?php

namespace App\Dominio;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Models\Cotizacion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * El listado de cotizaciones en un archivo que Excel abre con doble clic.
 *
 * Es CSV y no .xlsx a proposito: no necesita librerias nuevas, que en un
 * hosting sin SSH hay que subir a mano. Para que Excel en español lo abra
 * bien, sin asistente de importacion:
 *
 * - Separador `;`, el que Excel usa cuando la coma es el decimal.
 * - Marca BOM de UTF-8 al principio: sin ella, Excel lee las tildes como
 *   "CotizaciÃ³n".
 * - Montos en pesos enteros, sin separador de miles: Excel los toma como
 *   numeros y se pueden sumar.
 *
 * Los totales salen del calculo, como en la oferta, no de la columna
 * guardada: en el sistema anterior el Excel exportaba esa columna, y segun
 * la cotizacion traia el total de una version vieja del codigo o cero.
 */
final class ExportarCotizaciones
{
    private const BOM = "\xEF\xBB\xBF";

    private const COLUMNAS = [
        'Código', 'Nombre del servicio', 'Cliente', 'Gerencia', 'Autor', 'Creada',
        'Inicio estimado', 'Estado', 'Costo directo', 'AIU', 'Subtotal', 'IVA', 'Total con IVA',
    ];

    public function __construct(private readonly CalcularCotizacion $calculo) {}

    public static function crear(): self
    {
        return new self(CalcularCotizacion::crear());
    }

    /** @param  Builder<Cotizacion>  $consulta  Ya filtrada por alcance y por lo que haya en el listado */
    public function aCsv(Builder $consulta): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, self::BOM);
        fputcsv($salida, self::COLUMNAS, ';', '"', '');

        $consulta->with(['items', 'aiu', 'autor'])->chunk(100, function ($cotizaciones) use ($salida): void {
            foreach ($cotizaciones as $cotizacion) {
                fputcsv($salida, $this->fila($cotizacion), ';', '"', '');
            }
        });

        rewind($salida);
        $csv = (string) stream_get_contents($salida);
        fclose($salida);

        return $csv;
    }

    public static function nombreDeArchivo(): string
    {
        return 'cotizaciones-'.now()->format('Y-m-d-His').'.csv';
    }

    /** @return list<string|int> */
    private function fila(Cotizacion $cotizacion): array
    {
        $totales = $this->calculo->para($cotizacion)->totales;

        return [
            $this->texto($cotizacion->codigo),
            $this->texto($cotizacion->nombre),
            $this->texto($cotizacion->cliente),
            $this->texto($cotizacion->gerencia),
            $this->texto($cotizacion->autor?->name),
            $cotizacion->created_at?->format('Y-m-d') ?? '',
            $cotizacion->estimado_inicio ? Carbon::parse($cotizacion->estimado_inicio)->format('Y-m-d') : '',
            EstadoAprobacion::desdeColumnas($cotizacion->aprobacion1, $cotizacion->aprobacion2)->etiquetaCorta(),
            (int) round($totales->costoDirecto),
            (int) round($totales->aiuEnPesos()),
            (int) round($totales->subtotal),
            (int) round($totales->iva),
            (int) round($totales->total),
        ];
    }

    /**
     * Texto limpio y a salvo de formulas.
     *
     * Excel ejecuta una celda que empieza por =, +, - o @ como formula: un
     * nombre de cliente asi podria disparar algo al abrir el archivo. Se le
     * antepone un apostrofo, que Excel no muestra.
     */
    private function texto(?string $valor): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '');

        return preg_match('/^[=+\-@]/', $limpio) ? "'".$limpio : $limpio;
    }
}
