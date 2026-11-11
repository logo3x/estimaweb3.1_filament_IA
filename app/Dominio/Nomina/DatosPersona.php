<?php

namespace App\Dominio\Nomina;

use App\Models\Personal;

/**
 * Los datos de una persona que intervienen en su liquidacion.
 *
 * Se construye una vez desde el modelo y a partir de ahi el calculo trabaja
 * con tipos ciertos: booleanos de verdad en lugar de cadenas, y cifras ya
 * resueltas en lugar de relaciones que habria que recorrer en mitad de una
 * formula.
 */
final readonly class DatosPersona
{
    /**
     * @param  list<array{valor: float, condicion: string}>  $auxiliosConvencionales
     * @param  list<array{valor: float, condicion: string}>  $otrosAuxilios
     */
    public function __construct(
        public string $nombre,
        public float $salario,
        public bool $esRolMensual,
        public float $auxilioSinIncidencia,
        public float $capacitaciones,
        public float $porcentajeHorasExtra,
        public float $horasExtraDiurnas,
        public float $horasExtraNocturnas,
        public float $horasExtraFestivasDiurnas,
        public float $horasExtraFestivasNocturnas,
        public float $dominicalesFestivos,
        public float $dominicalesHabituales,
        public float $recargosNocturnos,
        public bool $cotizaPrestaciones,
        public bool $tieneAuxilioEducacion,
        public ?float $diasTrabajadosAlMes,
        public float $costoDotacion = 0.0,
        public ?float $duracionDotacion = null,
        public float $costoExamenes = 0.0,
        public ?float $duracionExamenes = null,
        public array $auxiliosConvencionales = [],
        public array $otrosAuxilios = [],
    ) {}

    /**
     * Construye los datos desde el modelo guardado.
     *
     * Conviene cargar antes las relaciones (turno, epps.catalogo,
     * examenesVacunas.catalogo, auxiliosAdicionales) para no provocar una
     * consulta por persona.
     */
    public static function desdeModelo(Personal $personal): self
    {
        [$costoDotacion, $duracionDotacion] = self::resumirAsignaciones($personal->epps);
        [$costoExamenes, $duracionExamenes] = self::resumirAsignaciones($personal->examenesVacunas);

        return new self(
            nombre: (string) $personal->nombre,
            salario: (float) $personal->salario,
            esRolMensual: str_contains((string) $personal->rol, 'Mensual'),
            auxilioSinIncidencia: (float) $personal->auxiliosin,
            capacitaciones: (float) $personal->capacitaciones,
            porcentajeHorasExtra: (float) $personal->p_he,
            horasExtraDiurnas: (float) $personal->hedo,
            horasExtraNocturnas: (float) $personal->heno,
            horasExtraFestivasDiurnas: (float) $personal->hedf,
            horasExtraFestivasNocturnas: (float) $personal->henf,
            dominicalesFestivos: (float) $personal->donfesoca,
            dominicalesHabituales: (float) $personal->donhabitual,
            recargosNocturnos: (float) $personal->rn,
            cotizaPrestaciones: self::esAfirmativo($personal->f_prestacional),
            tieneAuxilioEducacion: self::esAfirmativo($personal->aux_educacion),
            diasTrabajadosAlMes: $personal->turno?->d_trabajadosmes === null
                ? null
                : (float) $personal->turno->d_trabajadosmes,
            costoDotacion: $costoDotacion,
            duracionDotacion: $duracionDotacion,
            costoExamenes: $costoExamenes,
            duracionExamenes: $duracionExamenes,
            auxiliosConvencionales: self::resumirAuxilios($personal->auxiliosConvencionales ?? collect()),
            otrosAuxilios: self::resumirAuxilios($personal->auxiliosAdicionales),
        );
    }

    /**
     * Interpreta una bandera de la base, venga como venga.
     *
     * Aqui se corrige un fallo del sistema actual: el formulario guarda estas
     * banderas como 0 y 1 enteros, pero el calculo las compara con 'SI' y
     * 'Si', de modo que esas ramas NUNCA se ejecutan. Quien marca el auxilio
     * de educacion no lo recibe, y quien cotiza prestaciones se liquida con
     * el factor de quien no las cotiza.
     *
     * Verificado sobre los datos reales: ocho registros afectados, todos en
     * cotizaciones de prueba, pero el fallo esta latente para cualquier
     * cotizacion nueva.
     */
    private static function esAfirmativo(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_int($valor)) {
            return $valor === 1;
        }

        $texto = strtoupper(trim((string) $valor));

        return in_array($texto, ['1', 'SI', 'SÍ', 'TRUE', 'Y'], true);
    }

    /**
     * Costo total y duracion de un grupo de asignaciones (EPP o examenes).
     *
     * La duracion se toma de la primera fila, como hace el sistema actual: el
     * formulario la copia de la cotizacion, asi que todas las filas de una
     * persona llevan el mismo valor.
     *
     * @param  iterable<object>  $asignaciones
     * @return array{float, ?float}
     */
    private static function resumirAsignaciones(iterable $asignaciones): array
    {
        $costo = 0.0;
        $duracion = null;

        foreach ($asignaciones as $asignacion) {
            $precio = (float) ($asignacion->catalogo->precio ?? 0);
            $costo += $precio * (int) $asignacion->cantidad;

            $duracion ??= (float) $asignacion->duracion_servicio;
        }

        return [$costo, $duracion];
    }

    /**
     * @param  iterable<object>  $auxilios
     * @return list<array{valor: float, condicion: string}>
     */
    private static function resumirAuxilios(iterable $auxilios): array
    {
        $resumen = [];

        foreach ($auxilios as $auxilio) {
            $resumen[] = [
                'valor' => (float) $auxilio->valor,
                'condicion' => (string) $auxilio->condicion,
            ];
        }

        return $resumen;
    }
}
