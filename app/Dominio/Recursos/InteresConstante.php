<?php

namespace App\Dominio\Recursos;

/**
 * Tabla de interes constante por periodo de evaluacion.
 *
 * Son valores precalculados de una amortizacion financiera: cuanto se ajusta
 * el costo mensual de un activo segun los anos en que se deprecia. Un periodo
 * corto castiga el costo (hasta -17,6 por ciento) y uno largo lo encarece
 * (+43,7 por ciento a 24 anos).
 *
 * Aqui se corrige un fallo del sistema actual que nunca llego a notarse: la
 * tabla se indexa con claves como "12", pero MySQL devuelve el periodo como
 * "12.00", asi que la busqueda falla SIEMPRE y el interes vale cero para toda
 * herramienta y todo equipo. La formula existe pero nunca se aplico.
 *
 * Afecta a cotizaciones reales: siete, donde herramientas y equipos pesan
 * entre el 1,8 y el 10 por ciento del costo. Decidido con el usuario: la
 * version nueva aplica el interes como estaba disenado.
 */
final class InteresConstante
{
    /**
     * Interes por periodo, en anos.
     *
     * @var array<string, float>
     */
    private const TABLA = [
        '1' => 0.0525865366152938,
        '1.5' => -0.123611063,
        '2' => -0.164056738,
        '2.5' => -0.17560827,
        '3' => -0.175717616,
        '3.5' => -0.170121406,
        '4' => -0.161283177,
        '4.5' => -0.150440088,
        '5' => -0.138282094,
        '5.5' => -0.125224202,
        '6' => -0.111530726,
        '6.5' => -0.097377691,
        '7' => -0.082886587,
        '7.5' => -0.068143731,
        '8' => -0.053211934,
        '8.5' => -0.038137811,
        '9' => -0.022956528,
        '9.5' => -0.007694981,
        '10' => 0.007626037,
        '10.5' => 0.022990296,
        '11' => 0.03838505,
        '11.5' => 0.053800229,
        '12' => 0.069227858,
        '12.5' => 0.084661607,
        '13' => 0.100096463,
        '13.5' => 0.115528462,
        '14' => 0.130954492,
        '14.5' => 0.146372136,
        '15' => 0.161779541,
        '15.5' => 0.177175321,
        '16' => 0.192558475,
        '16.5' => 0.207928323,
        '17' => 0.223284448,
        '17.5' => 0.238626656,
        '18' => 0.253954937,
        '18.5' => 0.269269436,
        '19' => 0.284570427,
        '19.5' => 0.29985829,
        '20' => 0.315133497,
        '20.5' => 0.330396594,
        '21' => 0.345648187,
        '21.5' => 0.360888935,
        '22' => 0.376119536,
        '22.5' => 0.391340724,
        '23' => 0.406553257,
        '23.5' => 0.421757915,
        '24' => 0.436955493,
        '24.5' => 0.452146796,
        '25' => 0.467332639,    ];

    /**
     * El interes que corresponde a un periodo de evaluacion.
     *
     * Normaliza el formato antes de buscar: "12.00", "12.0" y 12 son el mismo
     * periodo, y el sistema actual solo encontraba el ultimo.
     *
     * Devuelve cero si el periodo no esta en la tabla, que es lo que hace el
     * sistema actual para periodos no contemplados.
     */
    public static function para(float|string|null $periodo): float
    {
        if ($periodo === null) {
            return 0.0;
        }

        return self::TABLA[self::normalizar($periodo)] ?? 0.0;
    }

    /** Si el periodo existe en la tabla, para poder avisar cuando no. */
    public static function conoce(float|string|null $periodo): bool
    {
        return $periodo !== null && isset(self::TABLA[self::normalizar($periodo)]);
    }

    /**
     * Lleva cualquier representacion del periodo a la clave de la tabla.
     *
     * "12.00" se convierte en "12", "1.50" en "1.5" y 2.0 en "2".
     */
    private static function normalizar(float|string $periodo): string
    {
        $numero = (float) $periodo;

        /** Hasta un decimal: la tabla avanza de medio en medio ano. */
        $texto = rtrim(rtrim(number_format($numero, 1, '.', ''), '0'), '.');

        return $texto === '' ? '0' : $texto;
    }
}
