<?php

namespace App\Dominio\Recursos;

use App\Dominio\Aiu\UnidadDeCobro;
use App\Dominio\Nomina\CalculadoraNomina;
use App\Dominio\Nomina\DatosPersona;
use App\Models\Revision;
use Illuminate\Support\Collection;

/**
 * Recorre los recursos de una cotizacion y acumula el costo de cada item.
 *
 * El sistema actual resuelve esto con once metodos procesarX de unas treinta
 * lineas cada uno, con parametros por referencia y un acumulador distinto por
 * categoria. Al mirarlos de cerca solo hay DOS comportamientos:
 *
 * - Recursos por TIEMPO (personal, herramientas, equipos): la unidad de la
 *   revision decide el precio, porque no cuesta lo mismo un dia que un mes.
 * - Recursos por CANTIDAD (el resto): el recurso ya trae su valor y la unidad
 *   es solo una etiqueta.
 *
 * De ahi que una unidad no prevista hiciera valer cero a los del primer grupo
 * y no afectara a los del segundo.
 */
final class MotorDeCostos
{
    public function __construct(
        private readonly CalculadoraNomina $nomina,
        private readonly CalculadoraAmortizacion $amortizacion,
    ) {}

    /**
     * Costo directo de cada item, antes de AIU y de IVA.
     *
     * @param  Collection<int, Revision>  $revisiones  Con sus recursos ya cargados
     * @param  array<int, int>  $cantidadPorItem  Cantidad de cada item de la cotizacion
     * @return array{costos: array<int, float>, lineas: list<LineaDeCosto>, avisos: list<string>}
     */
    public function calcular(Collection $revisiones, array $cantidadPorItem): array
    {
        $costos = [];
        $lineas = [];
        $avisos = [];

        foreach ($revisiones as $revision) {
            $itemId = (int) $revision->id_item_cotizacion;

            if ($itemId === 0) {
                continue;
            }

            $linea = $this->procesar($revision, $cantidadPorItem[$itemId] ?? 1, $avisos);

            if ($linea === null) {
                continue;
            }

            $costos[$itemId] = ($costos[$itemId] ?? 0.0) + $linea->aporte;
            $lineas[] = $linea;
        }

        return ['costos' => $costos, 'lineas' => $lineas, 'avisos' => $avisos];
    }

    /**
     * Una revision: que recurso es, cuanto vale y cuanto aporta al item.
     *
     * @param  list<string>  $avisos
     */
    private function procesar(Revision $revision, int $cantidadDelItem, array &$avisos): ?LineaDeCosto
    {
        $recurso = $revision->recurso();

        if ($recurso === null) {
            return null;
        }

        $tipo = (string) $revision->tipoDeRecurso();
        $unidad = (string) $revision->unidad;

        $valorUnitario = match ($tipo) {
            'personal' => $this->valorDePersonal($recurso, $unidad, $revision, $avisos),
            'herramienta', 'equipo' => $this->valorAmortizado($recurso, $unidad, $tipo, $revision, $avisos),
            'consumible' => $this->valorDeConsumible($recurso),
            default => (float) ($recurso->cantidad ?? 0),
        };

        return new LineaDeCosto(
            itemId: (int) $revision->id_item_cotizacion,
            categoria: ucfirst($tipo),
            descripcion: (string) ($recurso->nombre ?? ''),
            cantidad: (float) $revision->cantidad,
            unidad: $unidad,
            valorUnitario: $valorUnitario,
            aporte: $valorUnitario * (float) $revision->cantidad * $cantidadDelItem,
        );
    }

    /**
     * Costo de una persona en la unidad pedida.
     *
     * @param  list<string>  $avisos
     */
    private function valorDePersonal(object $personal, string $unidad, Revision $revision, array &$avisos): float
    {
        $costo = $this->nomina->calcular(DatosPersona::desdeModelo($personal));

        return match (UnidadDeCobro::desdeTexto($unidad)) {
            UnidadDeCobro::Mes => $costo->porMes,
            UnidadDeCobro::DiaCalendario => $costo->porDiaCalendario,
            UnidadDeCobro::DiaHabil => $costo->porDiaHabil,
            default => $this->avisarUnidadDesconocida('personal', $personal, $unidad, $revision, $avisos),
        };
    }

    /**
     * Costo amortizado de una herramienta o un equipo.
     *
     * @param  list<string>  $avisos
     */
    private function valorAmortizado(
        object $activo,
        string $unidad,
        string $tipo,
        Revision $revision,
        array &$avisos,
    ): float {
        $valorCompra = $this->valorDeCompra($activo);

        $argumentos = [
            $valorCompra,
            $activo->periodo_evaluacion,
            (float) ($activo->vrepo_anual ?? 0),
            (float) ($activo->vcalibracion_anual ?? 0),
        ];

        return match (UnidadDeCobro::desdeTexto($unidad)) {
            UnidadDeCobro::Mes => $this->amortizacion->porMes(...$argumentos),
            UnidadDeCobro::DiaHabil, UnidadDeCobro::DiaCalendario => $this->amortizacion->porDiaHabil(...$argumentos),
            default => $this->avisarUnidadDesconocida($tipo, $activo, $unidad, $revision, $avisos),
        };
    }

    /** Los consumibles se gastan: cuesta su compra completa, sin amortizar. */
    private function valorDeConsumible(object $consumible): float
    {
        return $this->valorDeCompra($consumible);
    }

    /** Suma lo que cuestan los items que componen un recurso. */
    private function valorDeCompra(object $recurso): float
    {
        $total = 0.0;

        foreach ($recurso->items ?? [] as $item) {
            $total += (int) $item->cantidad * (float) ($item->catalogo->precio ?? 0);
        }

        return $total;
    }

    /**
     * Registra que una unidad no se pudo interpretar, y devuelve cero.
     *
     * El sistema actual tambien devuelve cero aqui, pero en silencio: un
     * recurso bien cargado desaparece del precio sin que nadie lo note. El
     * aviso no cambia el numero, pero deja constancia de que algo quedo
     * fuera del calculo.
     *
     * @param  list<string>  $avisos
     */
    private function avisarUnidadDesconocida(
        string $tipo,
        object $recurso,
        string $unidad,
        Revision $revision,
        array &$avisos,
    ): float {
        $avisos[] = sprintf(
            'Revision %d: %s "%s" usa la unidad "%s", que no permite calcular un precio. Aporta cero.',
            $revision->id,
            $tipo,
            (string) ($recurso->nombre ?? '?'),
            $unidad,
        );

        return 0.0;
    }
}
