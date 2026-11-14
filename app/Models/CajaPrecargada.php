<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Base de las cajas precargadas.
 *
 * Una caja es una lista de elementos del catalogo guardada con nombre -"Caja
 * de Soldador", "Kit Herramientas Mecanicas"- que pertenece a quien la creo.
 * Al cotizar se vuelca entera en un grupo, en vez de buscar los elementos de
 * uno en uno.
 *
 * Herramientas, equipos, consumibles y EPP tienen cada uno su tabla, pero
 * todas con la misma forma: nombre, dueño y lista de elementos. Por eso las
 * cuatro clases concretas solo declaran sus nombres de tabla y de columna.
 *
 * En produccion las tablas se llaman `precargado_*`, con la errata incluida
 * (`precargado_heramientas`, con una sola erre). No se corrige: la comparte
 * el sistema que esta en produccion.
 */
abstract class CajaPrecargada extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'id_user',
    ];

    /** La clase que guarda cada elemento de la caja. */
    abstract public static function claseDeElemento(): string;

    /** Columna por la que los elementos apuntan a la caja. */
    abstract public static function columnaDeLaCaja(): string;

    /** Columna por la que cada elemento apunta al catalogo. */
    abstract public static function columnaDelCatalogo(): string;

    /** @return HasMany<Model, $this> */
    public function elementos(): HasMany
    {
        return $this->hasMany(static::claseDeElemento(), static::columnaDeLaCaja());
    }

    /** @return BelongsTo<User, $this> */
    public function duenio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Las cajas que ese usuario puede usar.
     *
     * Cada quien ve solo las suyas. Las que tienen `id_user` nulo son datos
     * de ejemplo que llegaron con la copia de produccion; no se muestran a
     * nadie, porque no son de nadie.
     */
    public function scopeDe(Builder $query, ?User $usuario): Builder
    {
        if (! $usuario) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('id_user', $usuario->id);
    }

    /**
     * Los ids del catalogo que componen la caja.
     *
     * Descarta los elementos que no apuntan a nada: en los datos reales hay
     * ocho asi, todos de una caja de ejemplo de EPP. Cargarlos crearia
     * lineas sin precio que no suman al costo y nadie sabe de donde salen.
     *
     * @return list<int>
     */
    public function idsDelCatalogo(): array
    {
        return $this->elementos()
            ->whereNotNull(static::columnaDelCatalogo())
            ->pluck(static::columnaDelCatalogo())
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** Cuantos elementos utiles tiene, para mostrarlo al elegir. */
    public function cuantosElementos(): int
    {
        return $this->elementos()
            ->whereNotNull(static::columnaDelCatalogo())
            ->count();
    }
}
