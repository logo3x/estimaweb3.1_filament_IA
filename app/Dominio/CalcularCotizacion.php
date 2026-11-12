<?php

namespace App\Dominio;

use App\Dominio\Aiu\CalculadoraAiu;
use App\Dominio\Aiu\ParametrosAiu;
use App\Dominio\Nomina\CalculadoraNomina;
use App\Dominio\Nomina\FactoresLegales;
use App\Dominio\Recursos\CalculadoraAmortizacion;
use App\Dominio\Recursos\MotorDeCostos;
use App\Models\Cotizacion;
use App\Models\Revision;

/**
 * Calcula el precio de una cotizacion de punta a punta.
 *
 * Es el unico punto de entrada: las pantallas, el documento y los informes
 * piden el precio aqui. Asi no vuelve a ocurrir lo del sistema actual, donde
 * la misma cotizacion daba cifras distintas segun desde donde se mirara,
 * porque cada pantalla rehacia el calculo a su manera.
 *
 * Son tres pasos encadenados:
 *   1. El motor recorre los recursos y acumula el costo directo por item.
 *   2. La calculadora del AIU anade administracion, imprevistos y utilidad.
 *   3. Se reparte el resultado entre los items para obtener lo que ve el
 *      cliente.
 */
final class CalcularCotizacion
{
    public function __construct(
        private readonly MotorDeCostos $motor,
        private readonly CalculadoraAiu $aiu,
    ) {}

    /**
     * Construye el servicio con sus dependencias ya resueltas.
     *
     * Atajo para pruebas y comandos; en la aplicacion lo inyecta el
     * contenedor.
     */
    public static function crear(): self
    {
        return new self(
            new MotorDeCostos(
                new CalculadoraNomina(FactoresLegales::vigentes()),
                new CalculadoraAmortizacion,
            ),
            new CalculadoraAiu,
        );
    }

    public function para(Cotizacion $cotizacion): CotizacionCalculada
    {
        $cotizacion->loadMissing(['items', 'aiu']);

        $parametros = ParametrosAiu::desdeModelo($cotizacion->aiu);

        $cantidades = $cotizacion->items
            ->pluck('cantidad', 'id')
            ->map(static fn ($cantidad): int => max(1, (int) $cantidad))
            ->all();

        $costos = $this->motor->calcular($this->revisionesDe($cotizacion), $cantidades);

        $costoDirecto = array_sum($costos['costos']);

        $totales = $this->aiu->calcular($costoDirecto, $parametros);

        return new CotizacionCalculada(
            cotizacionId: (int) $cotizacion->id,
            parametros: $parametros,
            totales: $totales,
            costoPorItem: $costos['costos'],
            valorUnitarioPorItem: $this->valoresUnitarios($costos['costos'], $cantidades, $parametros),
            detalle: $costos['lineas'],
            avisos: $costos['avisos'],
        );
    }

    /**
     * Las revisiones con todo lo que el calculo necesitara.
     *
     * Se cargan por adelantado las relaciones de cada tipo de recurso. Sin
     * esto, calcular una cotizacion con cien revisiones dispara cientos de
     * consultas: el motor pide el recurso de cada fila por separado.
     *
     * @return \Illuminate\Support\Collection<int, Revision>
     */
    private function revisionesDe(Cotizacion $cotizacion)
    {
        return Revision::query()
            ->where('id_cotizacion', $cotizacion->id)
            ->with([
                'cotizacion:id,dias_dura_servicio',
            ])
            ->get();
    }

    /**
     * Lo que ve el cliente en cada linea de la oferta.
     *
     * Con el AIU oculto el item absorbe su parte del AIU, porque no hay
     * ninguna linea aparte que lo recoja. Con el AIU visible muestra su costo
     * directo. Nunca incluye IVA: la tabla lo suma una sola vez al final, y
     * meterlo aqui lo contaria dos veces.
     *
     * @param  array<int, float>  $costos
     * @param  array<int, int>  $cantidades
     * @return array<int, float>
     */
    private function valoresUnitarios(array $costos, array $cantidades, ParametrosAiu $parametros): array
    {
        $unitarios = [];

        foreach ($costos as $itemId => $costo) {
            $unitarios[$itemId] = $this->aiu->valorUnitarioDeItem(
                $costo,
                $cantidades[$itemId] ?? 1,
                $parametros,
            );
        }

        return $unitarios;
    }
}
