<?php

namespace App\Dominio\Aiu;

use InvalidArgumentException;

/**
 * Los parametros con que se calcula el precio final de una cotizacion.
 *
 * Es un objeto inmutable y sin dependencias: no sabe de Eloquent ni de la base
 * de datos. Eso permite probar las cuatro combinaciones del AIU sin tocar la
 * base, que es donde el sistema actual se volvia dificil de verificar.
 *
 * Las dos banderas definen cuatro escenarios con formulas distintas:
 *
 * | contrato_obra | mostrar_aiu | IVA grava          | El AIU va          |
 * |---------------|-------------|--------------------|--------------------|
 * | SI            | SI          | solo la utilidad   | desglosado aparte  |
 * | SI            | NO          | nada (ya incluido) | dentro de cada item|
 * | NO            | SI          | el total con AIU   | desglosado aparte  |
 * | NO            | NO          | el total con AIU   | dentro de cada item|
 */
final readonly class ParametrosAiu
{
    /** Tarifa de IVA vigente en Colombia. */
    public const TARIFA_IVA = 0.19;

    public function __construct(
        public float $administracion,
        public float $imprevistos,
        public float $utilidad,
        public bool $esContratoDeObra,
        public bool $muestraAiuDesglosado,
    ) {
        foreach ([
            'administracion' => $administracion,
            'imprevistos' => $imprevistos,
            'utilidad' => $utilidad,
        ] as $nombre => $valor) {
            if ($valor < 0) {
                throw new InvalidArgumentException("El porcentaje de {$nombre} no puede ser negativo.");
            }
        }
    }

    /**
     * Construye los parametros desde el modelo guardado.
     *
     * Las banderas viajan como texto 'SI'/'NO' en la base; aqui se traducen a
     * booleanos una sola vez, en lugar de comparar cadenas por todo el codigo
     * como ocurria antes.
     */
    public static function desdeModelo(?\App\Models\Aiu $aiu): self
    {
        if ($aiu === null) {
            return self::sinAiu();
        }

        return new self(
            administracion: (float) $aiu->administracion,
            imprevistos: (float) $aiu->imprevistos,
            utilidad: (float) $aiu->utilidad,
            esContratoDeObra: $aiu->esContratoDeObra(),
            muestraAiuDesglosado: $aiu->muestraAiuDesglosado(),
        );
    }

    /**
     * Cotizacion sin AIU configurado: el costo directo no se incrementa, pero
     * el IVA sigue aplicando como en un contrato que no es de obra.
     */
    public static function sinAiu(): self
    {
        return new self(
            administracion: 0.0,
            imprevistos: 0.0,
            utilidad: 0.0,
            esContratoDeObra: false,
            muestraAiuDesglosado: false,
        );
    }

    /**
     * Multiplicador que incorpora el AIU al costo directo.
     *
     * Con 10% de administracion, 5% de imprevistos y 8% de utilidad da 1.23.
     * Con los tres en cero da 1, y el costo queda intacto.
     */
    public function factor(): float
    {
        return 1 + ($this->porcentajeTotal() / 100);
    }

    /** Suma de los tres porcentajes. */
    public function porcentajeTotal(): float
    {
        return $this->administracion + $this->imprevistos + $this->utilidad;
    }

    /**
     * Si el AIU se aplica item por item.
     *
     * Con el AIU oculto el cliente no ve el desglose, asi que el incremento se
     * reparte dentro del precio de cada item. Con el AIU visible los items
     * muestran su costo directo y A+I+U van como lineas aparte.
     */
    public function seAplicaPorItem(): bool
    {
        return ! $this->muestraAiuDesglosado;
    }

    /** Nombre del escenario, util para diagnosticos y pruebas. */
    public function escenario(): string
    {
        return ($this->esContratoDeObra ? 'obra' : 'servicio')
            . '/' . ($this->muestraAiuDesglosado ? 'aiu-visible' : 'aiu-oculto');
    }
}
