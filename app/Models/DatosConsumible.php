<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catalogo de consumibles con su precio de referencia. */
class DatosConsumible extends Model
{
    /** @use HasFactory<\Database\Factories\DatosConsumibleFactory> */
    use HasFactory;

    protected $table = 'datos_consumibles';

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
