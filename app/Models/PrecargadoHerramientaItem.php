<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un elemento dentro de una caja precargada de herramientas.
 *
 * Solo guarda a que entrada del catalogo apunta: la cantidad se decide al
 * volcarla en la cotizacion, no al guardar la caja.
 */
class PrecargadoHerramientaItem extends Model
{
    /** @use HasFactory<\Database\Factories\PrecargadoHerramientaItemFactory> */
    use HasFactory;

    protected $table = 'precargado_item_heramientas';

    protected $fillable = [
        'id_precargado_herramienta',
        'id_datos_herramienta',
    ];

    /** @return BelongsTo<PrecargadoHerramienta, $this> */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(PrecargadoHerramienta::class, 'id_precargado_herramienta');
    }

    /** Entrada del catalogo de la que sale el precio. */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(DatosHerramienta::class, 'id_datos_herramienta');
    }
}
