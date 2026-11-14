<?php

namespace App\Models;

/**
 * Caja precargada de herramientas.
 *
 * Ver {@see CajaPrecargada} para como funcionan.
 *
 * La tabla se llama `precargado_heramientas`, con una sola erre. Es una
 * errata de produccion que no se corrige: la comparte el sistema vivo.
 */
class PrecargadoHerramienta extends CajaPrecargada
{
    protected $table = 'precargado_heramientas';

    public static function claseDeElemento(): string
    {
        return PrecargadoHerramientaItem::class;
    }

    public static function columnaDeLaCaja(): string
    {
        return 'id_precargado_herramienta';
    }

    public static function columnaDelCatalogo(): string
    {
        return 'id_datos_herramienta';
    }
}
