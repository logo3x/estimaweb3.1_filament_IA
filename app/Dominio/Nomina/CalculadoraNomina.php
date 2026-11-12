<?php

namespace App\Dominio\Nomina;

/**
 * Liquida lo que cuesta una persona dentro de una cotizacion.
 *
 * El sistema actual tiene este calculo escrito DOS veces, una para el rol
 * mensual y otra para el diario, con unas 160 lineas casi identicas cada una.
 * La unica diferencia real es de donde sale el salario y entre cuantos dias
 * se reparte; aqui eso son dos parametros y el calculo es uno solo.
 *
 * No toca la base: recibe los datos ya leidos y devuelve numeros.
 */
final class CalculadoraNomina
{
    /** Dias de nomina de un mes, por convencion laboral. */
    private const DIAS_NOMINA_MES = 30;

    /** Promedio real de dias por mes, que usa el rol diario. */
    private const DIAS_PROMEDIO_MES = 365 / 12;

    /** Promedio de los recargos de hora extra diurna (1,25) y nocturna (1,75). */
    private const PROMEDIO_HORA_EXTRA = (1.25 + 1.75) / 2;

    public function __construct(private readonly FactoresLegales $factores) {}

    /**
     * Costo de una persona contratada por mes.
     *
     * El salario que llega es mensual y se reparte entre 30 dias de nomina.
     */
    public function porRolMensual(DatosPersona $persona): CostoPersona
    {
        return $this->liquidar(
            persona: $persona,
            salarioMensual: $persona->salario,
            diasDeReparto: self::DIAS_NOMINA_MES,
        );
    }

    /**
     * Costo de una persona contratada por dia.
     *
     * El salario que llega es diario y se lleva a mes con el promedio real de
     * dias (365/12), no con los 30 de nomina.
     */
    public function porRolDiario(DatosPersona $persona): CostoPersona
    {
        return $this->liquidar(
            persona: $persona,
            salarioMensual: $persona->salario * self::DIAS_PROMEDIO_MES,
            diasDeReparto: self::DIAS_PROMEDIO_MES,
        );
    }

    /** Elige el rol segun como este contratada la persona. */
    public function calcular(DatosPersona $persona): CostoPersona
    {
        return $persona->esRolMensual
            ? $this->porRolMensual($persona)
            : $this->porRolDiario($persona);
    }

    /**
     * El calculo, una sola vez para ambos roles.
     *
     * Se trabaja en valores mensuales y al final se reparten entre los dias
     * que corresponda, en lugar de llevar dos juegos de variables en paralelo
     * como hace el sistema actual.
     */
    private function liquidar(
        DatosPersona $persona,
        float $salarioMensual,
        float $diasDeReparto,
    ): CostoPersona {
        $salarioDiario = $salarioMensual / $diasDeReparto;

        $recargosMes = $this->recargos($persona, $salarioDiario) * $diasDeReparto;

        /** Base sobre la que se calculan las prestaciones. */
        $baseMes = $salarioMensual
            + $recargosMes
            + $this->auxiliosConvencionalesMes($persona, $diasDeReparto);

        $auxilioEducacion = $persona->tieneAuxilioEducacion ? $this->factores->auxilioEducacion : 0;
        $auxilioTransporte = $this->factores->auxilioTransportePara($salarioMensual);
        $otrosAuxiliosMes = $this->otrosAuxiliosMes($persona, $diasDeReparto);

        /**
         * En rol mensual estos valores ya vienen por mes; en rol diario
         * vienen por dia y hay que llevarlos a mes.
         */
        $auxilioSinIncidenciaMes = $persona->esRolMensual
            ? $persona->auxilioSinIncidencia
            : $persona->auxilioSinIncidencia * $diasDeReparto;

        $capacitacionMes = $persona->esRolMensual
            ? $persona->capacitaciones
            : $persona->capacitaciones * $diasDeReparto;

        /**
         * El ingreso decide que factor prestacional aplica. Incluye los
         * auxilios porque la norma mira el ingreso total de la persona.
         */
        $ingresoMes = $baseMes
            + $auxilioEducacion
            + $auxilioTransporte
            + $otrosAuxiliosMes
            + $auxilioSinIncidenciaMes;

        $factor = $this->factores->factorPrestacional(
            salarioMensual: $salarioMensual,
            ingresoMensual: $ingresoMes,
            cotizaPrestaciones: $persona->cotizaPrestaciones,
        );

        $prestacionesMes = $baseMes * $factor;

        $dotacionMes = $this->prorratearAMes($persona->costoDotacion, $persona->duracionDotacion);
        $examenesMes = $this->prorratearAMes($persona->costoExamenes, $persona->duracionExamenes);

        $costoMes = $baseMes
            + $prestacionesMes
            + $auxilioTransporte
            + $auxilioEducacion
            + $dotacionMes
            + $examenesMes
            + $otrosAuxiliosMes
            + $auxilioSinIncidenciaMes
            + $capacitacionMes;

        /**
         * El costo diario NO es el mensual dividido entre los dias.
         *
         * Cada componente se reparte sobre su propia base: el salario y los
         * auxilios entre los dias de nomina, pero la dotacion y los examenes
         * entre los dias de su duracion. Dividir el total daria un numero
         * parecido pero distinto -- unos seis pesos sobre siete mil en los
         * datos reales --, y la referencia es lo que hoy ve el usuario.
         */
        $costoDia = $baseMes / $diasDeReparto
            + $prestacionesMes / $diasDeReparto
            + $auxilioTransporte / $diasDeReparto
            + $auxilioEducacion / $diasDeReparto
            + $this->prorratearADia($persona->costoDotacion, $persona->duracionDotacion)
            + $this->prorratearADia($persona->costoExamenes, $persona->duracionExamenes)
            + $otrosAuxiliosMes / $diasDeReparto
            + $auxilioSinIncidenciaMes / $diasDeReparto
            + $capacitacionMes / $diasDeReparto;

        return new CostoPersona(
            porMes: $costoMes,
            porDiaCalendario: $costoDia,
            porDiaHabil: $this->porDiaHabil($costoMes, $persona->diasTrabajadosAlMes),
            salarioBase: $salarioMensual,
            recargos: $recargosMes,
            prestaciones: $prestacionesMes,
            auxilios: $auxilioTransporte + $auxilioEducacion + $otrosAuxiliosMes + $auxilioSinIncidenciaMes,
            dotacionYExamenes: $dotacionMes + $examenesMes,
        );
    }

    /**
     * Recargos de un dia: horas extra y trabajo nocturno o festivo.
     *
     * Cada uno multiplica el valor de la hora por el porcentaje de ley y por
     * las horas que la persona trabaja en esa condicion.
     */
    private function recargos(DatosPersona $persona, float $salarioDiario): float
    {
        $hora = $this->factores->valorHora($salarioDiario);

        $horasExtra = $salarioDiario * self::PROMEDIO_HORA_EXTRA * ($persona->porcentajeHorasExtra / 100);

        $diaFamilia = ($this->factores->diaFamilia / 100) * $salarioDiario;

        return $horasExtra
            + $diaFamilia
            + $hora * $this->factores->horaExtraDiurna * $persona->horasExtraDiurnas
            + $hora * $this->factores->horaExtraNocturna * $persona->horasExtraNocturnas
            + $hora * $this->factores->horaExtraFestivaDiurna * $persona->horasExtraFestivasDiurnas
            + $hora * $this->factores->horaExtraFestivaNocturna * $persona->horasExtraFestivasNocturnas
            + $hora * $this->factores->dominicalFestivo * $persona->dominicalesFestivos
            + $hora * $this->factores->diaHabitual * $persona->dominicalesHabituales
            + $hora * $this->factores->recargoNocturno * $persona->recargosNocturnos;
    }

    /**
     * Auxilios de convencion colectiva llevados a mes.
     *
     * La condicion decide el multiplicador: por dias de nomina o por los dias
     * que la persona realmente trabaja segun su turno.
     */
    private function auxiliosConvencionalesMes(DatosPersona $persona, float $diasDeReparto): float
    {
        return $this->sumarAuxilios($persona->auxiliosConvencionales, $persona, $diasDeReparto);
    }

    private function otrosAuxiliosMes(DatosPersona $persona, float $diasDeReparto): float
    {
        return $this->sumarAuxilios($persona->otrosAuxilios, $persona, $diasDeReparto);
    }

    /**
     * @param  list<array{valor: float, condicion: string}>  $auxilios
     */
    private function sumarAuxilios(array $auxilios, DatosPersona $persona, float $diasDeReparto): float
    {
        $total = 0.0;

        foreach ($auxilios as $auxilio) {
            $total += match ($auxilio['condicion']) {
                'Dias del mes' => $auxilio['valor'] * $diasDeReparto,
                'Dia Trabajado' => $auxilio['valor'] * $persona->diasTrabajadosAlMes,
                default => 0.0,
            };
        }

        return $total;
    }

    /**
     * Reparte un costo de dotacion o examenes a lo largo de su duracion.
     *
     * Una duracion sin definir aporta cero: el sistema actual dividia sin
     * comprobarlo y la cotizacion entera no se podia abrir.
     */
    private function prorratearAMes(float $costo, ?float $duracionEnDias): float
    {
        if ($duracionEnDias === null || $duracionEnDias <= 0) {
            return 0.0;
        }

        return ($costo * self::DIAS_NOMINA_MES) / $duracionEnDias;
    }

    /**
     * El mismo reparto, pero por dia.
     *
     * No es el mensual entre treinta: la dotacion se reparte entre los dias
     * que dura, que es un numero distinto.
     */
    private function prorratearADia(float $costo, ?float $duracionEnDias): float
    {
        if ($duracionEnDias === null || $duracionEnDias <= 0) {
            return 0.0;
        }

        return $costo / $duracionEnDias;
    }

    /**
     * Costo por dia habil: reparte el mes entre los dias que se trabajan.
     *
     * Sale mas caro que el dia calendario, porque el mismo costo se concentra
     * en menos dias.
     */
    private function porDiaHabil(float $costoMes, ?float $diasTrabajadosAlMes): float
    {
        if ($diasTrabajadosAlMes === null || $diasTrabajadosAlMes <= 0) {
            return 0.0;
        }

        return $costoMes / $diasTrabajadosAlMes;
    }
}
