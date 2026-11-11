<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Auxilio o bonificacion adicional de una persona. */
class PerAuxOtro extends Model
{
    /** @use HasFactory<\Database\Factories\PerAuxOtroFactory> */
    use HasFactory;

    protected $fillable = [
        'id_personal',
        'condicion',
        'nombre',
        'valor',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valor' => 'integer',
        ];
    }

    /** @return BelongsTo<Personal, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'id_personal');
    }
}
