<?php

namespace App\Dominio\Nomina;

use App\Models\OrigenFactor;
use RuntimeException;

/**
 * Los factores de ley con que se liquida la nomina.
 *
 * Son valores que cambian con la normativa, tipicamente cada enero con el
 * salario minimo. El sistema actual los lee con OrigenFactor::find(1) desde
 * dentro del calculo; aqui se reciben como dependencia, de modo que una
 * prueba puede fijarlos sin tocar la base.
 *
 * Si no estan cargados, el calculo debe detenerse en vez de multiplicar por
 * cero: una nomina con factores en cero da un costo irreal sin mostrar error.
 */
final readonly class FactoresLegales
{
    /** Por encima de 13 salarios minimos el contrato es de salario integral. */
    private const SMLV_SALARIO_INTEGRAL = 13;

    /** Umbral de 10 salarios minimos que cambia el factor prestacional. */
    private const SMLV_UMBRAL_PRESTACIONAL = 10;

    /** Hasta dos salarios minimos se tiene derecho a auxilio de transporte. */
    private const SMLV_TOPE_AUXILIO_TRANSPORTE = 2;

    public function __construct(
        /** Recargos por hora extra diurna, nocturna, festiva diurna y festiva nocturna. */
        public float $horaExtraDiurna,
        public float $horaExtraNocturna,
        public float $horaExtraFestivaDiurna,
        public float $horaExtraFestivaNocturna,
        /** Recargos por dominical/festivo, dia habitual y recargo nocturno. */
        public float $dominicalFestivo,
        public float $diaHabitual,
        public float $recargoNocturno,
        /** Porcentaje del dia de la familia, sobre el salario diario. */
        public float $diaFamilia,
        /** Factor prestacional de salario integral. */
        public float $factorIntegral,
        /** Con prestaciones, por debajo y por encima de 10 salarios minimos. */
        public float $factorConPrestacionesBajo,
        public float $factorConPrestacionesAlto,
        /** Sin prestaciones, por debajo y por encima de 10 salarios minimos. */
        public float $factorSinPrestacionesBajo,
        public float $factorSinPrestacionesAlto,
        public int $auxilioTransporte,
        public int $auxilioEducacion,
        public float $horasTrabajadasPorDia,
        public int $salarioMinimo,
    ) {
        if ($this->horasTrabajadasPorDia <= 0) {
            throw new RuntimeException(
                'Las horas trabajadas por dia deben ser mayores que cero: dividen el salario diario.'
            );
        }

        if ($this->salarioMinimo <= 0) {
            throw new RuntimeException(
                'El salario minimo debe ser mayor que cero: decide el factor prestacional.'
            );
        }
    }

    /**
     * Carga los factores vigentes desde la base.
     *
     * @throws RuntimeException si nadie los ha cargado
     */
    public static function vigentes(): self
    {
        $factores = OrigenFactor::vigentes();

        if ($factores === null) {
            throw new RuntimeException(
                'No hay factores legales cargados. Sin ellos no se puede liquidar ninguna nomina.'
            );
        }

        return self::desdeModelo($factores);
    }

    public static function desdeModelo(OrigenFactor $origen): self
    {
        return new self(
            horaExtraDiurna: (float) $origen->HEDO,
            horaExtraNocturna: (float) $origen->HENO,
            horaExtraFestivaDiurna: (float) $origen->HEDF,
            horaExtraFestivaNocturna: (float) $origen->HENF,
            dominicalFestivo: (float) $origen->DYFO,
            diaHabitual: (float) $origen->DH,
            recargoNocturno: (float) $origen->RN,
            diaFamilia: (float) $origen->DIAF,
            factorIntegral: (float) $origen->FPMI,
            factorConPrestacionesBajo: (float) $origen->FEME10,
            factorConPrestacionesAlto: (float) $origen->FEMA10,
            factorSinPrestacionesBajo: (float) $origen->FNME10,
            factorSinPrestacionesAlto: (float) $origen->FNMA10,
            auxilioTransporte: (int) $origen->aux_trans,
            auxilioEducacion: (int) $origen->aux_edu,
            horasTrabajadasPorDia: (float) $origen->Pro_horas_trabajadasdia,
            salarioMinimo: (int) $origen->Salario_min_vigente,
        );
    }

    /** Por encima de 13 salarios minimos el contrato es de salario integral. */
    public function esSalarioIntegral(float $salarioMensual): bool
    {
        return $salarioMensual > self::SMLV_SALARIO_INTEGRAL * $this->salarioMinimo;
    }

    /** El auxilio de transporte solo aplica hasta dos salarios minimos. */
    public function auxilioTransportePara(float $salarioMensual): int
    {
        return $salarioMensual < self::SMLV_TOPE_AUXILIO_TRANSPORTE * $this->salarioMinimo
            ? $this->auxilioTransporte
            : 0;
    }

    /**
     * El factor prestacional que corresponde a una persona.
     *
     * Tres condiciones encadenadas:
     * 1. Salario integral (>13 SMLV): el factor integral, sin mas preguntas.
     * 2. Si no, segun cotice o no prestaciones.
     * 3. Dentro de cada caso, segun el ingreso supere o no 10 SMLV.
     */
    public function factorPrestacional(
        float $salarioMensual,
        float $ingresoMensual,
        bool $cotizaPrestaciones,
    ): float {
        if ($this->esSalarioIntegral($salarioMensual)) {
            return $this->factorIntegral;
        }

        $ingresoBajo = $ingresoMensual < self::SMLV_UMBRAL_PRESTACIONAL * $this->salarioMinimo;

        if ($cotizaPrestaciones) {
            return $ingresoBajo ? $this->factorConPrestacionesBajo : $this->factorConPrestacionesAlto;
        }

        return $ingresoBajo ? $this->factorSinPrestacionesBajo : $this->factorSinPrestacionesAlto;
    }

    /**
     * Valor de una hora de trabajo a partir del salario diario.
     *
     * Base de todos los recargos: cada uno multiplica esta hora por su
     * porcentaje de ley y por las horas que la persona trabaja en esa
     * condicion.
     */
    public function valorHora(float $salarioDiario): float
    {
        return $salarioDiario / $this->horasTrabajadasPorDia;
    }
}
