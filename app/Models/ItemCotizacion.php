<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una linea de la oferta, tal como la ve el cliente.
 *
 * Los recursos se reparten entre los items a traves de Revision, y de ese
 * reparto sale el valor unitario que aparece en el documento final.
 */
class ItemCotizacion extends Model
{
    /** @use HasFactory<\Database\Factories\ItemCotizacionFactory> */
    use HasFactory;

    protected $table = 'item_cotizaciones';

    protected $fillable = [
        'id_cotizacion',
        'numero',
        'nombre',
        'unidad',
        'cantidad',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'cantidad' => 'integer',
        ];
    }

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /** Recursos repartidos a este item. */
    public function revisiones(): HasMany
    {
        return $this->hasMany(Revision::class, 'id_item_cotizacion');
    }
}
