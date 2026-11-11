<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Reparte un recurso entre los items de la cotizacion.
 *
 * Cada fila dice: "de este recurso, esta cantidad corresponde a este item".
 * Es la entrada del calculo del precio.
 *
 * Sobre las once columnas id_*: una fila llena exactamente una y deja diez en
 * NULL (verificado sobre los datos reales: 345 de 345). Es una relacion
 * polimorfica escrita a mano antes de usar la que Laravel ya trae.
 *
 * El esquema no se puede cambiar todavia -- ambas versiones del sistema deben
 * compartir base para poder compararse --, pero si se puede encapsular: el
 * resto del codigo usa recurso() y tipoDeRecurso() y no vuelve a preguntar
 * cual de las once columnas vino llena. Cuando el esquema migre a un morphTo
 * de verdad, cambia este archivo y nada mas.
 */
class Revision extends Model
{
    /** @use HasFactory<\Database\Factories\RevisionFactory> */
    use HasFactory;

    /**
     * Columna que guarda cada tipo de recurso, y su modelo.
     *
     * @var array<string, class-string<Model>>
     */
    public const RECURSOS = [
        'id_personal' => Personal::class,
        'id_herramienta' => Herramienta::class,
        'id_equipo' => Equipo::class,
        'id_consumible' => Consumible::class,
        'id_vehiculo' => Vehiculo::class,
        'id_oficina' => Oficina::class,
        'id_aseo' => Aseo::class,
        'id_servicio' => Servicio::class,
        'id_viatico' => Viatico::class,
        'id_comunicacion' => Comunicacion::class,
        'id_otros' => Otro::class,
    ];

    protected $fillable = [
        'id_cotizacion',
        'id_item_cotizacion',
        'cantidad',
        'unidad',
        'id_personal',
        'id_herramienta',
        'id_equipo',
        'id_consumible',
        'id_vehiculo',
        'id_oficina',
        'id_aseo',
        'id_servicio',
        'id_viatico',
        'id_comunicacion',
        'id_otros',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemCotizacion::class, 'id_item_cotizacion');
    }

    /**
     * Columna id_* que trae valor en esta fila, o null si ninguna.
     *
     * Que haya mas de una llena seria un dato corrupto: el sistema asume en
     * todas partes que una revision apunta a un unico recurso.
     */
    public function columnaDelRecurso(): ?string
    {
        foreach (array_keys(self::RECURSOS) as $columna) {
            if ($this->{$columna} !== null) {
                return $columna;
            }
        }

        return null;
    }

    /**
     * Nombre legible del tipo de recurso ('personal', 'herramienta', ...).
     * Util para agrupar el costo por categoria.
     */
    public function tipoDeRecurso(): ?string
    {
        $columna = $this->columnaDelRecurso();

        return $columna === null ? null : substr($columna, 3);
    }

    /**
     * El recurso al que apunta esta revision, sea del tipo que sea.
     *
     * Devuelve null si la fila quedo sin recurso, que no deberia ocurrir pero
     * se tolera para no reventar al recorrer datos heredados.
     */
    public function recurso(): ?Model
    {
        $columna = $this->columnaDelRecurso();

        if ($columna === null) {
            return null;
        }

        /** @var class-string<Model> $modelo */
        $modelo = self::RECURSOS[$columna];

        return $modelo::query()->find($this->{$columna});
    }

    /**
     * Apunta esta revision a un recurso, limpiando las otras diez columnas.
     *
     * Asignar id_* a mano es justo lo que deja filas con dos recursos; este
     * metodo lo hace imposible.
     *
     * @throws InvalidArgumentException si el modelo no es un recurso valido
     */
    public function asignarRecurso(Model $recurso): static
    {
        $columna = array_search($recurso::class, self::RECURSOS, true);

        if ($columna === false) {
            throw new InvalidArgumentException(
                $recurso::class . ' no es un recurso valido de una revision.'
            );
        }

        foreach (array_keys(self::RECURSOS) as $otra) {
            $this->{$otra} = null;
        }

        $this->{$columna} = $recurso->getKey();

        return $this;
    }

    /**
     * Limita la consulta a las revisiones de un tipo de recurso.
     *
     * Ejemplo: Revision::query()->deTipo(Personal::class)->get()
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Revision>  $query
     * @param  class-string<Model>  $modelo
     * @return \Illuminate\Database\Eloquent\Builder<Revision>
     */
    public function scopeDeTipo($query, string $modelo)
    {
        $columna = array_search($modelo, self::RECURSOS, true);

        if ($columna === false) {
            throw new InvalidArgumentException("{$modelo} no es un recurso valido de una revision.");
        }

        return $query->whereNotNull($columna);
    }
}
