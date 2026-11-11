<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vehiculo asignado a una cotizacion.
 *
 * movilizacion esta vacia en todos los datos reales; se conserva por
 * compatibilidad hasta que el inventario confirme que nadie la usa.
 */
class Vehiculo extends Model
{
    /** @use HasFactory<\Database\Factories\VehiculoFactory> */
    use HasFactory;

    protected $table = 'vehiculos';

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
