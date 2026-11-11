<?php

namespace App\Dominio\Recursos;

/**
 * Lo que cuesta al mes tener disponible una herramienta o un equipo.
 *
 * No es el precio de compra: es su amortizacion, repartida a lo largo del
 * periodo en que se deprecia, mas lo que cuesta reponerlo y calibrarlo cada
 * ano.
 *
 * Herramientas, equipos y consumibles comparten exactamente esta estructura
 * en el sistema actual, en tres bloques de codigo casi identicos. Aqui es uno.
 */
final class CalculadoraAmortizacion
{
    /** Tasa de oportunidad anual con que se traen los costos a presente. */
    private const TASA_ANUAL = 0.1;

    /**
     * Dias habiles de un mes.
     *
     * Del ano se descuentan los sabados (52), los domingos (52) y los
     * festivos (18), y el resto se reparte entre doce meses.
     */
    private const DIAS_HABILES_MES = (365 - 52 - 52 - 18) / 12;

    /**
     * Costo mensual de un activo.
     *
     * @param  float  $valorCompra  Precio total de los items que lo componen
     * @param  float|string|null  $periodoEvaluacion  Anos en que se deprecia
     * @param  float  $porcentajeReposicionAnual  Reposicion anual, en porcentaje
     * @param  float  $porcentajeCalibracionAnual  Calibracion anual, en porcentaje
     */
    public function porMes(
        float $valorCompra,
        float|string|null $periodoEvaluacion,
        float $porcentajeReposicionAnual,
        float $porcentajeCalibracionAnual,
    ): float {
        $periodo = (float) $periodoEvaluacion;

        if ($periodo <= 0 || $valorCompra <= 0) {
            return 0.0;
        }

        /**
         * Amortizacion mensual ajustada por el interes del periodo y traida a
         * valor presente con la tasa de oportunidad.
         */
        $interes = InteresConstante::para($periodoEvaluacion);

        $mensualVencido = $valorCompra / (12 * $periodo) * (1 + $interes);
        $mensualAnticipado = $mensualVencido * (1 + self::TASA_ANUAL) ** (1 / 12);

        $reposicion = $valorCompra * ($porcentajeReposicionAnual / 100) / 12;
        $calibracion = $valorCompra * ($porcentajeCalibracionAnual / 100) / 12;

        return $mensualAnticipado + $reposicion + $calibracion;
    }

    /**
     * Costo por dia habil.
     *
     * Reparte el costo mensual entre los dias que realmente se trabaja, no
     * entre los treinta del mes.
     */
    public function porDiaHabil(
        float $valorCompra,
        float|string|null $periodoEvaluacion,
        float $porcentajeReposicionAnual,
        float $porcentajeCalibracionAnual,
    ): float {
        return $this->porMes(
            $valorCompra,
            $periodoEvaluacion,
            $porcentajeReposicionAnual,
            $porcentajeCalibracionAnual,
        ) / self::DIAS_HABILES_MES;
    }
}
