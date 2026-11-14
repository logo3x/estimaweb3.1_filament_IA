<?php

namespace App\Models;

/**
 * Caja precargada de EPP.
 *
 * Ver {@see CajaPrecargada} para como funcionan.
 *
 * OJO: en los datos reales ocho de sus elementos no apuntan a ningun
 * articulo del catalogo. `idsDelCatalogo()` los descarta.
 */
class PrecargadoEpp extends CajaPrecargada
{
    protected $table = 'precargado_epps';

    public static function claseDeElemento(): string
    {
        return PrecargadoEppItem::class;
    }

    public static function columnaDeLaCaja(): string
    {
        return 'id_precargado_epp';
    }

    public static function columnaDelCatalogo(): string
    {
        return 'id_datos_epps';
    }
}
