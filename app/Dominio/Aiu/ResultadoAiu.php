<?php

namespace App\Dominio\Aiu;

/**
 * Resultado del calculo del AIU sobre un costo directo.
 *
 * Separa con claridad los tres momentos del precio, que en el sistema actual
 * se confundian entre si y fueron el origen de los errores corregidos:
 *
 * - costoDirecto : lo que cuesta ejecutar, sin AIU ni IVA.
 * - subtotal     : costo directo mas AIU, todavia sin IVA. Es la fila
 *                  SUBTOTAL que ve el cliente, y con la que debe cuadrar la
 *                  suma de los items.
 * - total        : subtotal mas IVA. Lo que se factura.
 */
final readonly class ResultadoAiu
{
    public function __construct(
        public float $costoDirecto,
        public float $administracion,
        public float $imprevistos,
        public float $utilidad,
        public float $subtotal,
        public float $iva,
        public float $total,
    ) {}

    /** Suma de los tres componentes del AIU en pesos. */
    public function aiuEnPesos(): float
    {
        return $this->administracion + $this->imprevistos + $this->utilidad;
    }

    /**
     * Redondeado a dos decimales, que es la precision con que se factura.
     *
     * @return array<string, float>
     */
    public function aArray(): array
    {
        return [
            'costo_directo' => round($this->costoDirecto, 2),
            'administracion' => round($this->administracion, 2),
            'imprevistos' => round($this->imprevistos, 2),
            'utilidad' => round($this->utilidad, 2),
            'subtotal' => round($this->subtotal, 2),
            'iva' => round($this->iva, 2),
            'total' => round($this->total, 2),
        ];
    }
}
