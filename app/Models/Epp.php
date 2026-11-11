<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catalogo de elementos de proteccion personal. */
class Epp extends Model
{
    /** @use HasFactory<\Database\Factories\EppFactory> */
    use HasFactory;

    protected $table = 'epps';

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
