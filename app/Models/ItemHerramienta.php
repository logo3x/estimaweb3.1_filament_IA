<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linea dentro de un grupo de Herramienta, tomada del catalogo.
 *
 * El nombre de la clave foranea (id_herramienta, id_datos_herramientas) reproduce el de
 * produccion, incluidas sus inconsistencias de singular y plural.
 */
class ItemHerramienta extends Model
{
    /** @use HasFactory<\Database\Factories\ItemHerramientaFactory> */
    use HasFactory;

    protected $table = 'item_herramientas';

    protected $fillable = [
        'id_herramienta',
        'id_datos_herramientas',
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

    /** @return BelongsTo<Herramienta, $this> */
    public function herramienta(): BelongsTo
    {
        return $this->belongsTo(Herramienta::class, 'id_herramienta');
    }

    /** Entrada del catalogo de la que sale el precio. */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(DatosHerramienta::class, 'id_datos_herramientas');
    }
}
