<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auxilio de convencion colectiva de una persona.
 *
 * La condicion decide como se suma al mes: "Dias del mes" lo multiplica por
 * los 30 dias de nomina, "Dia Trabajado" por los dias que la persona trabaja
 * segun su turno.
 */
class PerAuxConvencional extends Model
{
    /** @use HasFactory<\Database\Factories\PerAuxConvencionalFactory> */
    use HasFactory;

    protected $table = 'per_aux_convencionales';

    protected $fillable = [
        'id_personal',
        'condicion',
        'nombre',
        'valor',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valor' => 'integer',
        ];
    }

    /** @return BelongsTo<Personal, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'id_personal');
    }
}
