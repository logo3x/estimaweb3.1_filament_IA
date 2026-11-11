<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Factores legales con que se calcula la nomina.
 *
 * Es una sola fila que el area administrativa actualiza cuando cambia la
 * normativa, tipicamente en enero con el salario minimo. Sin ella no se puede
 * calcular el costo de ninguna persona: de aqui salen los recargos por hora
 * extra, el auxilio de transporte y el factor prestacional.
 *
 * El sistema actual la busca con find(1) directamente desde el servicio de
 * calculo. Aqui se usa vigentes(), que al menos deja claro el supuesto.
 */
class OrigenFactor extends Model
{
    /** @use HasFactory<\Database\Factories\OrigenFactorFactory> */
    use HasFactory;

    protected $fillable = [
        'HEDO',
        'HENO',
        'HEDF',
        'HENF',
        'DYFO',
        'DH',
        'RN',
        'DIAF',
        'FPMI',
        'FEME10',
        'FEMA10',
        'FNME10',
        'FNMA10',
        'aux_trans',
        'aux_edu',
        'Pro_horas_trabajadasdia',
        'Salario_min_vigente',
        'min_administracion',
        'min_imprevistos',
        'min_utilidad',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'HEDO' => 'float',
            'HENO' => 'float',
            'HEDF' => 'float',
            'HENF' => 'float',
            'DYFO' => 'float',
            'DH' => 'float',
            'RN' => 'float',
            'DIAF' => 'float',
            'FPMI' => 'float',
            'FEME10' => 'float',
            'FEMA10' => 'float',
            'FNME10' => 'float',
            'FNMA10' => 'float',
            'aux_trans' => 'integer',
            'aux_edu' => 'integer',
            'Pro_horas_trabajadasdia' => 'float',
            'Salario_min_vigente' => 'integer',
            'min_administracion' => 'float',
            'min_imprevistos' => 'float',
            'min_utilidad' => 'float',
        ];
    }

    /**
     * Los factores en uso.
     *
     * Devuelve null si nadie los ha cargado todavia; quien calcula debe
     * decidir que hacer, en vez de operar con ceros en silencio.
     */
    public static function vigentes(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    /** Dos salarios minimos: tope para tener derecho al auxilio de transporte. */
    public function topeAuxilioTransporte(): int
    {
        return $this->Salario_min_vigente * 2;
    }
}
