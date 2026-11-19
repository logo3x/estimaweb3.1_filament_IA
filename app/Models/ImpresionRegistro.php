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
        'tipo',
        'fecha_hora',
        'destinatario',
        'correo_destino',
        'usuario_id',
        'referencia',
        'fallo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'tipo' => \App\Dominio\Envios\TipoDeEnvio::class,
        ];
    }

    /** Si salio de la empresa, o solo se genero un documento. */
    public function salioAlCliente(): bool
    {
        return $this->tipo->salioAlCliente() && $this->fallo === null;
    }

    /** Los envios de una cotizacion, del mas reciente al mas viejo. */
    public function scopeRecientes(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderByDesc('fecha_hora');
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
