<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Oficina o instalacion asignada a una cotizacion.
 */
class Oficina extends Model
{
    /** @use HasFactory<\Database\Factories\OficinaFactory> */
    use HasFactory;

    protected $table = 'oficinas';

    protected $fillable = [
        'id_cotizacion',
        'nombre',
        'unidad',
        'cantidad',
        'movilizacion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'movilizacion' => 'integer',
        ];
    }

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }
}
