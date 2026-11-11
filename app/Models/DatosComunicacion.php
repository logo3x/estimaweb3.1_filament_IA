<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalogo de servicios de comunicaciones.
 *
 * Unico catalogo con dos precios: la unidad elegida en la cotizacion decide
 * cual se aplica.
 */
class DatosComunicacion extends Model
{
    /** @use HasFactory<\Database\Factories\DatosComunicacionFactory> */
    use HasFactory;

    protected $table = 'datos_comunicaciones';

    protected $fillable = [
        'codigo',
        'nombre',
        'grupo',
        'precio_dia',
        'precio_mes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'precio_dia' => 'integer',
            'precio_mes' => 'integer',
        ];
    }
}
