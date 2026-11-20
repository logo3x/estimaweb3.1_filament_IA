<?php

namespace App\Dominio;

use App\Dominio\Auditoria\Auditoria;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Crea una cotizacion nueva a partir de otra, con todo lo que la compone.
 *
 * Existe por lo que paso con la cotizacion 33: estaba aprobada y enviada al
 * cliente, y como no habia forma de copiarla, alguien la SOBRESCRIBIO para
 * preparar otra oferta parecida. La oferta aprobada dejo de existir en la
 * base. Reutilizar una cotizacion como plantilla es razonable; lo que hace
 * falta es que no destruya la original.
 *
 * Se copia: la cabecera, el AIU, los items, los recursos de las once
 * categorias con lo que cuelga de ellos (auxilios, EPP y examenes del
 * personal; componentes de herramientas, equipos y consumibles) y las
 * revisiones que los asignan a cada item, reapuntadas a las copias.
 *
 * NO se copia lo que es historia de la original: las aprobaciones, los
 * envios y la auditoria. La copia nace en borrador y es de quien la hizo.
 */
final class DuplicarCotizacion
{
    /**
     * Cada categoria de recurso y la columna de la revision que la señala.
     *
     * Los hijos son las relaciones del recurso que tambien se copian.
     *
     * @var array<string, array{columna: string, hijos: list<string>}>
     */
    private const RECURSOS = [
        'personals' => ['columna' => 'id_personal', 'hijos' => ['auxiliosAdicionales', 'auxiliosConvencionales', 'epps', 'examenesVacunas']],
        'herramientas' => ['columna' => 'id_herramienta', 'hijos' => ['items']],
        'equipos' => ['columna' => 'id_equipo', 'hijos' => ['items']],
        'consumibles' => ['columna' => 'id_consumible', 'hijos' => ['items']],
        'vehiculos' => ['columna' => 'id_vehiculo', 'hijos' => []],
        'oficinas' => ['columna' => 'id_oficina', 'hijos' => []],
        'aseos' => ['columna' => 'id_aseo', 'hijos' => []],
        'servicios' => ['columna' => 'id_servicio', 'hijos' => []],
        'viaticos' => ['columna' => 'id_viatico', 'hijos' => []],
        'comunicaciones' => ['columna' => 'id_comunicacion', 'hijos' => []],
        'otros' => ['columna' => 'id_otros', 'hijos' => []],
    ];

    /** Lo que es historia de la original y no pasa a la copia. */
    private const NO_SE_COPIA = [
        'aprobacion1', 'aprobacion1_fecha', 'aprobacion1_comentarios', 'aprobacion1_user_id',
        'aprobacion2', 'aprobacion2_fecha', 'aprobacion2_comentarios', 'aprobacion2_user_id',
        'estado',
    ];

    public function __construct(
        private readonly CalcularCotizacion $calculo,
        private readonly Auditoria $auditoria = new Auditoria,
    ) {}

    public static function crear(): self
    {
        return new self(CalcularCotizacion::crear());
    }

    /** Un codigo libre con el formato de la casa: EOEP-BOG-AAAAMMDD-NNN. */
    public static function codigoSugerido(): string
    {
        $siguiente = ((int) Cotizacion::query()->max('id')) + 1;

        return 'EOEP-BOG-'.now()->format('Ymd').'-'.str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
    }

    public function duplicar(Cotizacion $original, User $autor, string $codigo, string $nombre): Cotizacion
    {
        return DB::transaction(function () use ($original, $autor, $codigo, $nombre): Cotizacion {
            $copia = $original->replicate(self::NO_SE_COPIA);
            $copia->codigo = $codigo;
            $copia->nombre = $nombre;
            $copia->id_user = $autor->id;
            $copia->save();

            if ($original->aiu) {
                $aiu = $original->aiu->replicate();
                $aiu->id_cotizacion = $copia->id;
                $aiu->save();
            }

            $items = [];
            foreach ($original->items as $item) {
                $nuevo = $item->replicate();
                $nuevo->id_cotizacion = $copia->id;
                $nuevo->save();
                $items[$item->id] = $nuevo->id;
            }

            $recursos = [];
            foreach (self::RECURSOS as $relacion => $definicion) {
                $recursos[$definicion['columna']] = $this->copiarRecursos($original, $copia, $relacion, $definicion['hijos']);
            }

            foreach ($original->revisiones as $revision) {
                $nueva = $revision->replicate();
                $nueva->id_cotizacion = $copia->id;
                $nueva->id_item_cotizacion = $items[$revision->id_item_cotizacion] ?? null;

                foreach ($recursos as $columna => $mapa) {
                    if ($revision->{$columna} !== null) {
                        $nueva->{$columna} = $mapa[$revision->{$columna}] ?? $revision->{$columna};
                    }
                }

                $nueva->save();
            }

            $this->guardarTotales($copia->fresh());

            $this->auditoria->creado(
                $copia,
                "Copia de la cotización {$original->codigo} (id {$original->id})",
            );

            return $copia;
        });
    }

    /**
     * Copia los recursos de una categoria y lo que cuelga de ellos.
     *
     * @param  list<string>  $hijos
     * @return array<int, int> id original => id de la copia
     */
    private function copiarRecursos(Cotizacion $original, Cotizacion $copia, string $relacion, array $hijos): array
    {
        $mapa = [];

        foreach ($original->{$relacion}()->with($hijos)->get() as $recurso) {
            $nuevo = $recurso->replicate();
            $nuevo->id_cotizacion = $copia->id;
            $nuevo->save();
            $mapa[$recurso->id] = $nuevo->id;

            foreach ($hijos as $hijo) {
                $this->copiarHijos($recurso, $nuevo, $hijo);
            }
        }

        return $mapa;
    }

    private function copiarHijos(Model $original, Model $copia, string $relacion): void
    {
        $claveForanea = $original->{$relacion}()->getForeignKeyName();

        foreach ($original->{$relacion} as $hijo) {
            $nuevo = $hijo->replicate();
            $nuevo->{$claveForanea} = $copia->getKey();
            $nuevo->save();
        }
    }

    /** Los totales del listado, para que la copia no aparezca en cero hasta que alguien recalcule. */
    private function guardarTotales(Cotizacion $copia): void
    {
        $totales = $this->calculo->para($copia)->totales;

        $copia->update([
            'valor_total' => $totales->costoDirecto,
            'valor_total_con_aiu' => $totales->subtotal,
            'aiu_administracion' => $totales->administracion,
            'aiu_imprevistos' => $totales->imprevistos,
            'aiu_utilidad' => $totales->utilidad,
            'aiu_iva' => $totales->iva,
            'aiu_valortotal_con_iva' => $totales->total,
        ]);
    }
}
