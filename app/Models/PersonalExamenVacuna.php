<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Examen o vacuna asignado a una persona.
 *
 * duracion_servicio se copia de la cotizacion al asignar, para que un cambio
 * posterior en la duracion no altere el costo ya calculado.
 */
class PersonalExamenVacuna extends Model
{
    /** @use HasFactory<\Database\Factories\PersonalExamenVacunaFactory> */
    use HasFactory;

    protected $table = 'personal_examenes_vacunas';

    protected $fillable = [
        'personal_id',
        'exavacu_id',
        'cantidad',
        'duracion_servicio',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'duracion_servicio' => 'integer',
        ];
    }

    /** @return BelongsTo<Personal, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    /** @return BelongsTo<ExamenVacuna, $this> */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(ExamenVacuna::class, 'exavacu_id');
    }
}
