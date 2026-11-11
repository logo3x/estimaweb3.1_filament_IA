<?php

namespace App\Dominio\Nomina;

/**
 * Lo que cuesta una persona, en las tres unidades con que se cobra.
 *
 * El dia habil y el dia calendario no son lo mismo: el habil reparte el costo
 * mensual entre los dias que se trabajan segun el turno, asi que sale mas
 * caro que el calendario, que lo reparte entre los 30 dias de nomina.
 */
final readonly class CostoPersona
{
    public function __construct(
        public float $porMes,
        public float $porDiaCalendario,
        public float $porDiaHabil,
        /** Desglose, para poder revisar de donde sale el total. */
        public float $salarioBase,
        public float $recargos,
        public float $prestaciones,
        public float $auxilios,
        public float $dotacionYExamenes,
    ) {}

    /** @return array<string, float> */
    public function aArray(): array
    {
        return [
            'por_mes' => round($this->porMes, 2),
            'por_dia_calendario' => round($this->porDiaCalendario, 2),
            'por_dia_habil' => round($this->porDiaHabil, 2),
            'salario_base' => round($this->salarioBase, 2),
            'recargos' => round($this->recargos, 2),
            'prestaciones' => round($this->prestaciones, 2),
            'auxilios' => round($this->auxilios, 2),
            'dotacion_y_examenes' => round($this->dotacionYExamenes, 2),
        ];
    }
}
