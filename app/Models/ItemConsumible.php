<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linea dentro de un grupo de Consumible, tomada del catalogo.
 *
 * El nombre de la clave foranea (id_consumibles, id_datos_consumibles) reproduce el de
 * produccion, incluidas sus inconsistencias de singular y plural.
 */
class ItemConsumible extends Model
{
    /** @use HasFactory<\Database\Factories\ItemConsumibleFactory> */
    use HasFactory;

    protected $table = 'item_consumibles';

    protected $fillable = [
        'id_consumibles',
        'id_datos_consumibles',
        'unidad',
        'cantidad',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    /** @return BelongsTo<Consumible, $this> */
    public function consumible(): BelongsTo
    {
        return $this->belongsTo(Consumible::class, 'id_consumibles');
    }

    /** Entrada del catalogo de la que sale el precio. */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(DatosConsumible::class, 'id_datos_consumibles');
    }
}
