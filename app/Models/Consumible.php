<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo de consumibles de una cotizacion.
 *
 * A diferencia de Herramienta y Equipo, sus tres valores son nullable: en
 * produccion los consumibles no registran reposicion ni calibracion.
 */
class Consumible extends Model
{
    /** @use HasFactory<\Database\Factories\ConsumibleFactory> */
    use HasFactory;

    protected $table = 'consumibles';

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

    /** @return HasMany<ItemConsumible, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ItemConsumible::class, 'id_consumibles');
    }
}
