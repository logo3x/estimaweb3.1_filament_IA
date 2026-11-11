<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo de herramientas de una cotizacion.
 *
 * Comparte estructura exacta con Equipo y Consumible: los tres agrupan items
 * tomados de su catalogo y aportan costo de reposicion y calibracion.
 */
class Herramienta extends Model
{
    /** @use HasFactory<\Database\Factories\HerramientaFactory> */
    use HasFactory;

    protected $table = 'herramientas';

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

    /** @return HasMany<ItemHerramienta, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ItemHerramienta::class, 'id_herramienta');
    }
}
