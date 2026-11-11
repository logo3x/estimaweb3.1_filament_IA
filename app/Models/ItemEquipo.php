<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linea dentro de un grupo de Equipo, tomada del catalogo.
 *
 * El nombre de la clave foranea (id_equipo, id_datos_equipo) reproduce el de
 * produccion, incluidas sus inconsistencias de singular y plural.
 */
class ItemEquipo extends Model
{
    /** @use HasFactory<\Database\Factories\ItemEquipoFactory> */
    use HasFactory;

    protected $table = 'item_equipos';

    protected $fillable = [
        'id_equipo',
        'id_datos_equipo',
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

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'id_equipo');
    }

    /** Entrada del catalogo de la que sale el precio. */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(DatosEquipo::class, 'id_datos_equipo');
    }
}
