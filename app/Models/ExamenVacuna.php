<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catalogo de examenes medicos y vacunas. */
class ExamenVacuna extends Model
{
    /** @use HasFactory<\Database\Factories\ExamenVacunaFactory> */
    use HasFactory;

    protected $table = 'examenes_vacunas';

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
