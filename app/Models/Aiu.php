<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parametros del AIU (Administracion, Imprevistos, Utilidad) de una cotizacion.
 *
 * Dos banderas deciden como se calcula el precio final, y entre ellas definen
 * cuatro escenarios distintos:
 *
 * - contrato_obra: en contrato de obra el IVA grava solo la utilidad; en los
 *   demas grava el total.
 * - mostrar_aiu: con el AIU visible, A+I+U aparecen desglosados aparte y los
 *   items muestran su costo directo. Con el AIU oculto, el factor se aplica
 *   item por item y el total ya lo trae incorporado.
 *
 * Las banderas se guardan como texto 'SI'/'NO' porque asi estan en produccion.
 * Los accesores de abajo evitan que esa comparacion de cadenas se repita por
 * todo el codigo, que fue el origen de los errores de calculo corregidos.
 */
class Aiu extends Model
{
    /** @use HasFactory<\Database\Factories\AiuFactory> */
    use HasFactory;

    protected $table = 'aius';

    protected $fillable = [
        'id_cotizacion',
        'contrato_obra',
        'mostrar_aiu',
        'contrato_actual',
        'administracion',
        'imprevistos',
        'utilidad',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'administracion' => 'float',
            'imprevistos' => 'float',
            'utilidad' => 'float',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /** En contrato de obra el IVA grava solo la utilidad. */
    public function esContratoDeObra(): bool
    {
        return (string) $this->contrato_obra === 'SI';
    }

    /** Con el AIU visible, A+I+U van desglosados y no dentro de cada item. */
    public function muestraAiuDesglosado(): bool
    {
        return (string) $this->mostrar_aiu === 'SI';
    }

    /**
     * Factor por el que se multiplica el costo directo para incorporar el AIU.
     *
     * Con 10% de administracion, 5% de imprevistos y 8% de utilidad devuelve
     * 1.23. Si los tres porcentajes son cero, devuelve 1 y el costo no cambia.
     */
    public function factor(): float
    {
        return 1 + (((float) $this->administracion
            + (float) $this->imprevistos
            + (float) $this->utilidad) / 100);
    }
}
