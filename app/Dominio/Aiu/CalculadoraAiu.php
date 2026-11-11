<?php

namespace App\Dominio\Aiu;

/**
 * Aplica el AIU y el IVA a un costo directo.
 *
 * Esta es LA formula del AIU. El sistema actual la tiene escrita tres veces
 * (en el servicio de calculo, en el componente Livewire y en el comando de
 * diagnostico), y esa duplicacion fue la causa de que un error apareciera en
 * una pantalla y no en otra. Aqui existe una sola vez.
 *
 * No toca la base de datos ni depende de Eloquent: recibe un numero y unos
 * parametros, y devuelve el desglose. Eso permite probar las cuatro
 * combinaciones del AIU directamente, sin montar una cotizacion completa.
 */
final class CalculadoraAiu
{
    /**
     * Calcula el precio final a partir del costo directo.
     *
     * El costo directo es la suma de lo que cuesta ejecutar el servicio
     * (personal, herramientas, equipos, ...), SIN AIU y sin IVA.
     */
    public function calcular(float $costoDirecto, ParametrosAiu $parametros): ResultadoAiu
    {
        $administracion = $costoDirecto * ($parametros->administracion / 100);
        $imprevistos = $costoDirecto * ($parametros->imprevistos / 100);
        $utilidad = $costoDirecto * ($parametros->utilidad / 100);

        $subtotal = $costoDirecto + $administracion + $imprevistos + $utilidad;

        $iva = $this->calcularIva($subtotal, $utilidad, $parametros);

        return new ResultadoAiu(
            costoDirecto: $costoDirecto,
            administracion: $administracion,
            imprevistos: $imprevistos,
            utilidad: $utilidad,
            subtotal: $subtotal,
            iva: $iva,
            total: $subtotal + $iva,
        );
    }

    /**
     * Calcula el precio cuando el costo recibido YA incorpora el AIU.
     *
     * Con el AIU oculto, el factor se aplica item por item al construir la
     * cotizacion, asi que el costo acumulado llega con el AIU dentro. Volver a
     * aplicarlo sobre ese total lo cobraria dos veces: en la cotizacion 55 eso
     * daba 815 millones en vez de 713.
     *
     * Para reconstruir el desglose que ve el cliente hay que ir hacia atras:
     * del total con AIU se despeja el costo directo dividiendo por el factor,
     * y de ahi salen administracion, imprevistos y utilidad.
     */
    public function calcularDesdeCostoConAiu(float $costoConAiu, ParametrosAiu $parametros): ResultadoAiu
    {
        $factor = $parametros->factor();

        /** Factor 1 significa AIU en cero: no hay nada que despejar. */
        $costoDirecto = $factor > 0 ? $costoConAiu / $factor : $costoConAiu;

        $administracion = $costoDirecto * ($parametros->administracion / 100);
        $imprevistos = $costoDirecto * ($parametros->imprevistos / 100);
        $utilidad = $costoDirecto * ($parametros->utilidad / 100);

        $iva = $this->calcularIva($costoConAiu, $utilidad, $parametros);

        return new ResultadoAiu(
            costoDirecto: $costoDirecto,
            administracion: $administracion,
            imprevistos: $imprevistos,
            utilidad: $utilidad,
            subtotal: $costoConAiu,
            iva: $iva,
            total: $costoConAiu + $iva,
        );
    }

    /**
     * El IVA segun el tipo de contrato.
     *
     * En un contrato de obra el IVA grava unicamente la utilidad, no el costo
     * directo ni la administracion: la norma entiende que ahi no hay valor
     * agregado gravable. En los demas contratos grava el subtotal completo.
     *
     * Que el AIU se muestre o no desglosado NO cambia cuanto IVA se paga: solo
     * cambia como se presenta en el documento. El sistema actual mezclaba
     * ambas cosas y por eso el mismo contrato daba cifras distintas segun la
     * pantalla.
     */
    private function calcularIva(float $subtotal, float $utilidad, ParametrosAiu $parametros): float
    {
        if ($parametros->esContratoDeObra) {
            return $utilidad * ParametrosAiu::TARIFA_IVA;
        }

        return $subtotal * ParametrosAiu::TARIFA_IVA;
    }

    /**
     * Precio unitario de un item, tal como lo ve el cliente en la oferta.
     *
     * Con el AIU oculto el item debe absorber su parte del AIU, porque no hay
     * ninguna linea aparte que lo recoja. Con el AIU visible el item muestra
     * su costo directo y el AIU aparece desglosado abajo.
     *
     * Nunca incluye IVA: la tabla de items suma el IVA una sola vez, al final.
     * Meterlo aqui lo contaba dos veces, y ese fue el error que reporto el
     * area comercial sobre la cotizacion 55.
     */
    public function valorUnitarioDeItem(
        float $costoDirectoDelItem,
        int $cantidad,
        ParametrosAiu $parametros,
    ): float {
        if ($cantidad <= 0) {
            return 0.0;
        }

        $porUnidad = $costoDirectoDelItem / $cantidad;

        return $parametros->seAplicaPorItem()
            ? $porUnidad * $parametros->factor()
            : $porUnidad;
    }

    /**
     * Reparte un costo a lo largo de su duracion.
     *
     * Una duracion en cero significa que el elemento no tiene plazo definido,
     * asi que su costo no se puede prorratear y aporta cero. El sistema actual
     * dividia sin comprobarlo y una duracion en cero hacia que la cotizacion
     * entera no se pudiera abrir.
     */
    public function prorratear(float $monto, ?float $duracion): float
    {
        if ($duracion === null || $duracion <= 0) {
            return 0.0;
        }

        return $monto / $duracion;
    }
}
