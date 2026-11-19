<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una linea de la auditoria: quien cambio que y cuando.
 *
 * El modelo **no tiene timestamps** propios: la fecha es `cuando`, y es la
 * del hecho auditado. Tampoco se actualiza nunca: una fila de auditoria se
 * escribe una vez y no se vuelve a tocar.
 */
class RegistroDeCambio extends Model
{
    protected $table = 'registro_de_cambios';

    public $timestamps = false;

    protected $fillable = [
        'entidad',
        'entidad_id',
        'accion',
        'cotizacion_id',
        'cambios',
        'resumen',
        'usuario_id',
        'ip',
        'cuando',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'cuando' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    /** Cuantos campos cambiaron en esta linea. */
    public function cuantosCampos(): int
    {
        return count($this->cambios ?? []);
    }

    /** Lo mas reciente primero, que es como se consulta. */
    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('cuando')->orderByDesc('id');
    }
}
