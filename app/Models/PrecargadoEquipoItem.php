<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un elemento dentro de una caja precargada de equipos.
 *
 * Solo guarda a que entrada del catalogo apunta: la cantidad se decide al
 * volcarla en la cotizacion, no al guardar la caja.
 */
class PrecargadoEquipoItem extends Model
{
    /** @use HasFactory<\Database\Factories\PrecargadoEquipoItemFactory> */
    use HasFactory;

    protected $table = 'precargado_item_equipos';

    protected $fillable = [
        'id_precargado_equipo',
        'id_datos_equipo',
    ];

    /** @return BelongsTo<PrecargadoEquipo, $this> */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(PrecargadoEquipo::class, 'id_precargado_equipo');
    }

    /** Entrada del catalogo de la que sale el precio. */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(DatosEquipo::class, 'id_datos_equipo');
    }
}
