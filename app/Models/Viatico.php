<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Viatico de una cotizacion.
 */
class Viatico extends Model
{
    /** @use HasFactory<\Database\Factories\ViaticoFactory> */
    use HasFactory;

    protected $table = 'viaticos';

    protected $fillable = [
        'id_cotizacion',
        'nombre',
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

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }
}
