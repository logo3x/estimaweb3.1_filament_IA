<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una persona asignada a la cotizacion, con su nomina.
 *
 * Es el recurso mas complejo del sistema: ademas del salario base lleva
 * auxilios, capacitaciones, horas extra y recargos. Los factores de horas y
 * recargos se heredan del turno al crearse, pero quedan editables por fila
 * porque una persona puede salirse del patron de su turno.
 */
class Personal extends Model
{
    /** @use HasFactory<\Database\Factories\PersonalFactory> */
    use HasFactory;

    protected $table = 'personals';

    protected $fillable = [
        'id_cotizacion',
        'id_turno',
        'nombre',
        'salario',
        'auxiliosin',
        'capacitaciones',
        'p_he',
        'hedo',
        'heno',
        'hedf',
        'henf',
        'donfesoca',
        'donhabitual',
        'rn',
        'f_prestacional',
        'aux_educacion',
        'rol',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'salario' => 'integer',
            'auxiliosin' => 'integer',
            'capacitaciones' => 'integer',
            'p_he' => 'integer',
            'hedo' => 'float',
            'heno' => 'float',
            'hedf' => 'float',
            'henf' => 'float',
            'donfesoca' => 'float',
            'donhabitual' => 'float',
            'rn' => 'float',
        ];
    }

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /** @return BelongsTo<Turno, $this> */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'id_turno');
    }

    /** Auxilios y bonificaciones adicionales de esta persona. */
    public function auxiliosAdicionales(): HasMany
    {
        return $this->hasMany(PerAuxOtro::class, 'id_personal');
    }

    /** Elementos de proteccion personal asignados. */
    public function epps(): HasMany
    {
        return $this->hasMany(PersonalEpp::class, 'personal_id');
    }

    public function examenesVacunas(): HasMany
    {
        return $this->hasMany(PersonalExamenVacuna::class, 'personal_id');
    }

    /** El auxilio de educacion se guarda como bandera de texto SI/NO. */
    public function tieneAuxilioDeEducacion(): bool
    {
        return (string) $this->aux_educacion === 'SI';
    }
}
