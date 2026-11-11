<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Servicio de comunicaciones de una cotizacion.
 *
 * Referencia su catalogo porque el precio cambia segun se cobre por dia o
 * por mes, y esa eleccion vive en la unidad de esta fila.
 */
class Comunicacion extends Model
{
    /** @use HasFactory<\Database\Factories\ComunicacionFactory> */
    use HasFactory;

    protected $table = 'comunicaciones';

    protected $fillable = [
        'id_cotizacion',
        'id_datos_comunicaciones',
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

    /** @return BelongsTo<DatosComunicacion, $this> */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(DatosComunicacion::class, 'id_datos_comunicaciones');
    }
}
