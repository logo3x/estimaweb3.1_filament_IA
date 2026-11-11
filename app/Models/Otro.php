<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Costo que no encaja en las demas categorias.
 */
class Otro extends Model
{
    /** @use HasFactory<\Database\Factories\OtroFactory> */
    use HasFactory;

    protected $table = 'otros';

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
