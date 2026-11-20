<?php

namespace App\Dominio\Recursos;

/**
 * Una linea del detalle de costos de un item.
 *
 * Es lo que se ve al desglosar de donde sale el precio: que recurso, en que
 * cantidad y cuanto aporta.
 */
final readonly class LineaDeCosto
{
    public function __construct(
        public int $itemId,
        public string $categoria,
        public string $descripcion,
        public float $cantidad,
        public string $unidad,
        /** Costo unitario del recurso en la unidad indicada. */
        public float $valorUnitario,
        /** Lo que aporta al item, ya multiplicado por cantidades. */
        public float $aporte,
        /**
         * La revision de la que sale la linea.
         *
         * Permite rastrear cada cifra hasta el recurso cargado y compararla,
         * linea a linea, con el calculo del sistema actual.
         */
        public int $revisionId = 0,
    ) {}

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'revision_id' => $this->revisionId,
            'item_id' => $this->itemId,
            'categoria' => $this->categoria,
            'descripcion' => $this->descripcion,
            'cantidad' => $this->cantidad,
            'unidad' => $this->unidad,
            'valor_unitario' => round($this->valorUnitario, 2),
            'aporte' => round($this->aporte, 2),
        ];
    }
}
