<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Gerencia de la empresa. Agrupa usuarios y acota que cotizaciones ven. */
class Gerencia extends Model
{
    /** @use HasFactory<\Database\Factories\GerenciaFactory> */
    use HasFactory;

    protected $fillable = ['nombre'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'id_gerencia');
    }
}
