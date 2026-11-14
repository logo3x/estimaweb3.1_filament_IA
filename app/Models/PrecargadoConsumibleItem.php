<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un elemento dentro de una caja precargada de consumibles.
 *
 * Solo guarda a que entrada del catalogo apunta: la cantidad se decide al
 * volcarla en la cotizacion, no al guardar la caja.
 */
class PrecargadoConsumibleItem extends Model
{
    /** @use HasFactory<\Database\Factories\PrecargadoConsumibleItemFactory> */
    use HasFactory;

    protected $table = 'precargado_item_consumibles';

    protected $fillable = [
        'id_precargado_consumible',
        'id_datos_consumible',
    ];

    /** @return BelongsTo<PrecargadoConsumible, $this> */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(PrecargadoConsumible::class, 'id_precargado_consumible');
    }

    /** Entrada del catalogo de la que sale el precio. */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(DatosConsumible::class, 'id_datos_consumible');
    }
}
