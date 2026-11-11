<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de trabajo con sus factores de horas extra y recargos.
 *
 * Al asignar un turno a una persona, sus factores se copian a la fila de
 * Personal; desde ahi quedan editables sin afectar el catalogo.
 */
class Turno extends Model
{
    /** @use HasFactory<\Database\Factories\TurnoFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre',
        'd_trabajados',
        'd_descansados',
        'd_trabajadosmes',
        'hedo',
        'heno',
        'hedf',
        'henf',
        'donfesoca',
        'donhabitual',
        'rn',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'd_trabajados' => 'float',
            'd_descansados' => 'float',
            'd_trabajadosmes' => 'float',
            'hedo' => 'float',
            'heno' => 'float',
            'hedf' => 'float',
            'henf' => 'float',
            'donfesoca' => 'float',
            'donhabitual' => 'float',
            'rn' => 'float',
        ];
    }

    public function personals(): HasMany
    {
        return $this->hasMany(Personal::class, 'id_turno');
    }
}
