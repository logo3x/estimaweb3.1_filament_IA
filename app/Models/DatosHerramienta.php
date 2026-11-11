<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catalogo de herramientas con su precio de referencia. */
class DatosHerramienta extends Model
{
    /** @use HasFactory<\Database\Factories\DatosHerramientaFactory> */
    use HasFactory;

    protected $table = 'datos_herramientas';

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
