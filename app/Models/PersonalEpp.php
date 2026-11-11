<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elemento de proteccion asignado a una persona.
 *
 * duracion_servicio se copia de la cotizacion al asignar, para que un cambio
 * posterior en la duracion no altere el costo ya calculado.
 */
class PersonalEpp extends Model
{
    /** @use HasFactory<\Database\Factories\PersonalEppFactory> */
    use HasFactory;

    protected $table = 'personal_epps';

    protected $fillable = [
        'personal_id',
        'epps_id',
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

    /** @return BelongsTo<Epp, $this> */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Epp::class, 'epps_id');
    }
}
