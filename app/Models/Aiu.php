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
        return self::esAfirmativo($this->contrato_obra);
    }

    /** Con el AIU visible, A+I+U van desglosados y no dentro de cada item. */
    public function muestraAiuDesglosado(): bool
    {
        return self::esAfirmativo($this->mostrar_aiu);
    }

    /** Si el AIU se pacto sobre el contrato actual. */
    public function esContratoActual(): bool
    {
        return self::esAfirmativo($this->contrato_actual);
    }

    /**
     * Interpreta una bandera de la base, venga en el formato que venga.
     *
     * En esta tabla conviven DOS formatos: la mayoria de las filas guardan
     * 'SI'/'NO' y unas pocas guardan '1'/'0'. El sistema actual solo entiende
     * el primero, asi que una cotizacion guardada como '1' se calcula como si
     * dijera 'NO'.
     *
     * En los datos reales son dos cotizaciones de prueba, y el total
     * facturado coincide igualmente; lo que cambia es la presentacion, porque
     * el cliente deberia ver el AIU desglosado y lo ve incorporado en los
     * precios. Aun asi es un formato que hay que entender, no ignorar.
     */
    private static function esAfirmativo(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_int($valor)) {
            return $valor === 1;
        }

        /**
         * mb_strtoupper y no strtoupper: la funcion de PHP no entiende los
         * acentos en UTF-8, asi que "si" con tilde no se convertiria bien.
         */
        return in_array(mb_strtoupper(trim((string) $valor)), ['1', 'SI', 'SÍ', 'TRUE'], true);
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
