<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitacora de envios de una cotizacion: quien la genero, cuando y para quien.
 *
 * Sirve de respaldo ante un cliente que discute una oferta recibida.
 */
class ImpresionRegistro extends Model
{
    /** @use HasFactory<\Database\Factories\ImpresionRegistroFactory> */
    use HasFactory;

    protected $fillable = [
        'cotizacion_id',
        'fecha_hora',
        'destinatario',
        'usuario_id',
        'referencia',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
