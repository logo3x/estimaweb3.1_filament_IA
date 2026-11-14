<?php

namespace App\Models;

/**
 * Caja precargada de equipos.
 *
 * Ver {@see CajaPrecargada} para como funcionan.
 */
class PrecargadoEquipo extends CajaPrecargada
{
    protected $table = 'precargado_equipos';

    public static function claseDeElemento(): string
    {
        return PrecargadoEquipoItem::class;
    }

    public static function columnaDeLaCaja(): string
    {
        return 'id_precargado_equipo';
    }

    public static function columnaDelCatalogo(): string
    {
        return 'id_datos_equipo';
    }
}
