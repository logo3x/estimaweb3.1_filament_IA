<?php

namespace App\Models;

/**
 * Caja precargada de consumibles.
 *
 * Ver {@see CajaPrecargada} para como funcionan.
 */
class PrecargadoConsumible extends CajaPrecargada
{
    protected $table = 'precargado_consumibles';

    public static function claseDeElemento(): string
    {
        return PrecargadoConsumibleItem::class;
    }

    public static function columnaDeLaCaja(): string
    {
        return 'id_precargado_consumible';
    }

    public static function columnaDelCatalogo(): string
    {
        return 'id_datos_consumible';
    }
}
