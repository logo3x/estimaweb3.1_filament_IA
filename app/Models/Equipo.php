<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo de equipos de una cotizacion. Misma estructura que Herramienta.
 */
class Equipo extends Model
{
    /** @use HasFactory<\Database\Factories\EquipoFactory> */
    use HasFactory;

    protected $table = 'equipos';

    protected $fillable = [
        'id_cotizacion',
        'nombre',
        'periodo_evaluacion',
        'vrepo_anual',
        'vcalibracion_anual',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'periodo_evaluacion' => 'float',
            'vrepo_anual' => 'float',
            'vcalibracion_anual' => 'float',
        ];
    }

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /** @return HasMany<ItemEquipo, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ItemEquipo::class, 'id_equipo');
    }
}
