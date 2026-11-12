<?php

namespace App\Dominio;

use App\Dominio\Aiu\ParametrosAiu;
use App\Dominio\Aiu\ResultadoAiu;
use App\Dominio\Recursos\LineaDeCosto;

/**
 * El precio completo de una cotizacion, listo para mostrar o imprimir.
 *
 * Reune las tres capas del calculo: lo que cuesta cada item, lo que el AIU
 * anade y el IVA que corresponde segun el tipo de contrato.
 */
final readonly class CotizacionCalculada
{
    /**
     * @param  array<int, float>  $costoPorItem  Costo directo de cada item
     * @param  array<int, float>  $valorUnitarioPorItem  Lo que ve el cliente
     * @param  list<LineaDeCosto>  $detalle  De donde sale cada peso
     * @param  list<string>  $avisos  Recursos que no se pudieron calcular
     */
    public function __construct(
        public int $cotizacionId,
        public ParametrosAiu $parametros,
        public ResultadoAiu $totales,
        public array $costoPorItem,
        public array $valorUnitarioPorItem,
        public array $detalle,
        public array $avisos,
    ) {}

    /** Si algun recurso quedo fuera del precio por no poder calcularse. */
    public function tieneAvisos(): bool
    {
        return $this->avisos !== [];
    }

    /**
     * Suma de los items tal como la ve el cliente.
     *
     * Debe cuadrar con el subtotal cuando el AIU va incorporado, y diferir en
     * exactamente A+I+U cuando va desglosado aparte.
     *
     * @param  array<int, int>  $cantidadPorItem
     */
    public function sumaDeItems(array $cantidadPorItem): float
    {
        $suma = 0.0;

        foreach ($this->valorUnitarioPorItem as $itemId => $unitario) {
            $suma += $unitario * ($cantidadPorItem[$itemId] ?? 1);
        }

        return $suma;
    }

    /**
     * Costo agrupado por categoria de recurso.
     *
     * Responde a "cuanto de este precio es personal y cuanto herramientas",
     * que es la primera pregunta al revisar una cotizacion.
     *
     * @return array<string, float>
     */
    public function costoPorCategoria(): array
    {
        $porCategoria = [];

        foreach ($this->detalle as $linea) {
            $porCategoria[$linea->categoria] = ($porCategoria[$linea->categoria] ?? 0.0) + $linea->aporte;
        }

        arsort($porCategoria);

        return $porCategoria;
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'cotizacion_id' => $this->cotizacionId,
            'escenario' => $this->parametros->escenario(),
            'totales' => $this->totales->aArray(),
            'costo_por_item' => array_map(
                static fn (float $valor): float => round($valor, 2),
                $this->costoPorItem
            ),
            'valor_unitario_por_item' => array_map(
                static fn (float $valor): float => round($valor, 2),
                $this->valorUnitarioPorItem
            ),
            'costo_por_categoria' => array_map(
                static fn (float $valor): float => round($valor, 2),
                $this->costoPorCategoria()
            ),
            'avisos' => $this->avisos,
        ];
    }
}
