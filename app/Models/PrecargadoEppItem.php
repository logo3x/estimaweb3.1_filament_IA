<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un elemento dentro de una caja precargada de EPP.
 *
 * Solo guarda a que entrada del catalogo apunta: la cantidad se decide al
 * volcarla en la cotizacion, no al guardar la caja.
 */
class PrecargadoEppItem extends Model
{
    /** @use HasFactory<\Database\Factories\PrecargadoEppItemFactory> */
    use HasFactory;

    protected $table = 'precargado_item_epps';

    protected $fillable = [
        'id_precargado_epp',
        'id_datos_epps',
    ];

    /** @return BelongsTo<PrecargadoEpp, $this> */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(PrecargadoEpp::class, 'id_precargado_epp');
    }

    /** Entrada del catalogo de la que sale el precio. */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Epp::class, 'id_datos_epps');
    }
}
