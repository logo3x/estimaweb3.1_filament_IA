<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catalogo de equipos con su precio de referencia. */
class DatosEquipo extends Model
{
    /** @use HasFactory<\Database\Factories\DatosEquipoFactory> */
    use HasFactory;

    protected $table = 'datos_equipos';

    protected $fillable = [
        'codigo',
        'nombre',
        'grupo',
        'precio',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'precio' => 'integer',
        ];
    }
}
