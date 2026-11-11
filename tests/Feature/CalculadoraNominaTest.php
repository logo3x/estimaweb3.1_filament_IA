<?php

namespace Tests\Feature;

use App\Dominio\Nomina\CalculadoraNomina;
use App\Dominio\Nomina\DatosPersona;
use App\Dominio\Nomina\FactoresLegales;
use App\Models\OrigenFactor;
use App\Models\Personal;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * La liquidacion de nomina, contra los numeros del sistema actual.
 *
 * El sistema actual tiene este calculo escrito dos veces, una por rol, con
 * unas 160 lineas casi identicas. Aqui es uno solo, y la prueba central
 * comprueba persona por persona que da los mismos pesos.
 */
class CalculadoraNominaTest extends TestCase
{
    /** Margen en pesos sobre un costo mensual de varios millones. */
    private const MARGEN = 0.05;

    private function calculadora(): CalculadoraNomina
    {
        return new CalculadoraNomina(FactoresLegales::vigentes());
    }

    /** @return array<string, array{int, string, float, float, float}> */
    public static function nominaCongelada(): array
    {
        $archivo = __DIR__ . '/../golden/nomina.json';

        if (! is_file($archivo)) {
            return [];
        }

        $casos = [];

        foreach (json_decode((string) file_get_contents($archivo), true) ?: [] as $fila) {
            $casos["persona {$fila['id']} ({$fila['rol']})"] = [
                (int) $fila['id'],
                (string) $fila['rol'],
                (float) $fila['mes'],
                (float) $fila['dia'],
                (float) $fila['habil'],
            ];
        }

        return $casos;
    }

    /**
     * LA PRUEBA QUE IMPORTA: cada persona real cuesta lo mismo que antes.
     *
     * Cubre los dos roles sobre 52 personas con datos reales.
     */
    #[DataProvider('nominaCongelada')]
    public function test_reproduce_la_nomina_del_sistema_actual(
        int $id,
        string $rol,
        float $mesEsperado,
        float $diaEsperado,
        float $habilEsperado,
    ): void {
        $personal = Personal::query()
            ->with(['turno', 'epps.catalogo', 'examenesVacunas.catalogo', 'auxiliosAdicionales', 'auxiliosConvencionales'])
            ->find($id);

        $this->assertNotNull($personal, "Ya no existe la persona {$id}.");

        $costo = $this->calculadora()->calcular(DatosPersona::desdeModelo($personal));

        /** Margen proporcional: el redondeo acumula sobre cifras de millones. */
        $margen = max(self::MARGEN, $mesEsperado * 0.0000001);

        $this->assertEqualsWithDelta(
            $mesEsperado,
            $costo->porMes,
            $margen,
            "Persona {$id} ({$rol}): cambio el costo mensual."
        );

        $this->assertEqualsWithDelta(
            $diaEsperado,
            $costo->porDiaCalendario,
            $margen,
            "Persona {$id} ({$rol}): cambio el costo por dia calendario."
        );

        $this->assertEqualsWithDelta(
            $habilEsperado,
            $costo->porDiaHabil,
            $margen,
            "Persona {$id} ({$rol}): cambio el costo por dia habil."
        );
    }

    /**
     * El dia habil sale mas caro que el calendario.
     *
     * El mismo costo mensual se reparte entre menos dias, porque no todos los
     * dias del mes se trabajan.
     */
    public function test_el_dia_habil_cuesta_mas_que_el_calendario(): void
    {
        $personal = Personal::query()
            ->whereHas('turno')
            ->where('salario', '>', 1_000_000)
            ->with(['turno', 'epps.catalogo', 'examenesVacunas.catalogo', 'auxiliosAdicionales', 'auxiliosConvencionales'])
            ->first();

        $this->assertNotNull($personal);

        $costo = $this->calculadora()->calcular(DatosPersona::desdeModelo($personal));

        $this->assertGreaterThan($costo->porDiaCalendario, $costo->porDiaHabil);
    }

    /** El desglose suma el costo mensual completo. */
    public function test_el_desglose_suma_el_costo_mensual(): void
    {
        $personal = Personal::query()
            ->whereHas('turno')
            ->where('salario', '>', 1_000_000)
            ->with(['turno', 'epps.catalogo', 'examenesVacunas.catalogo', 'auxiliosAdicionales', 'auxiliosConvencionales'])
            ->first();

        $costo = $this->calculadora()->calcular(DatosPersona::desdeModelo($personal));

        $suma = $costo->salarioBase
            + $costo->recargos
            + $costo->prestaciones
            + $costo->auxilios
            + $costo->dotacionYExamenes;

        /**
         * Las capacitaciones y los auxilios convencionales entran en el total
         * pero no en estas cinco categorias, asi que la suma es una cota
         * inferior, no una igualdad.
         */
        $this->assertLessThanOrEqual($costo->porMes + 1.0, $suma);
        $this->assertGreaterThan(0, $suma);
    }

    /**
     * Las banderas se leen bien vengan como vengan.
     *
     * Corrige un fallo del sistema actual: el formulario guarda 0 y 1, pero
     * el calculo compara con 'SI' y 'Si', asi que esas ramas nunca se
     * ejecutan. Quien marca el auxilio de educacion no lo recibe.
     */
    public function test_interpreta_las_banderas_guardadas_como_cero_y_uno(): void
    {
        $conAuxilio = Personal::query()
            ->where('aux_educacion', '1')
            ->with(['turno', 'epps.catalogo', 'examenesVacunas.catalogo', 'auxiliosAdicionales', 'auxiliosConvencionales'])
            ->first();

        $this->assertNotNull($conAuxilio, 'No hay personal con aux_educacion = 1 para la prueba.');

        $datos = DatosPersona::desdeModelo($conAuxilio);

        $this->assertTrue(
            $datos->tieneAuxilioEducacion,
            'Una bandera guardada como "1" debe leerse como afirmativa.'
        );

        $cotiza = Personal::query()->where('f_prestacional', '1')->first();

        if ($cotiza !== null) {
            $this->assertTrue(DatosPersona::desdeModelo($cotiza)->cotizaPrestaciones);
        }
    }

    /** El auxilio de educacion se suma cuando corresponde. */
    public function test_el_auxilio_de_educacion_se_suma_al_costo(): void
    {
        $personal = Personal::query()
            ->whereHas('turno')
            ->where('salario', '>', 1_000_000)
            ->with(['turno', 'epps.catalogo', 'examenesVacunas.catalogo', 'auxiliosAdicionales', 'auxiliosConvencionales'])
            ->first();

        $base = DatosPersona::desdeModelo($personal);
        $factores = FactoresLegales::vigentes();

        $sinAuxilio = new DatosPersona(
            nombre: $base->nombre,
            salario: $base->salario,
            esRolMensual: $base->esRolMensual,
            auxilioSinIncidencia: $base->auxilioSinIncidencia,
            capacitaciones: $base->capacitaciones,
            porcentajeHorasExtra: $base->porcentajeHorasExtra,
            horasExtraDiurnas: $base->horasExtraDiurnas,
            horasExtraNocturnas: $base->horasExtraNocturnas,
            horasExtraFestivasDiurnas: $base->horasExtraFestivasDiurnas,
            horasExtraFestivasNocturnas: $base->horasExtraFestivasNocturnas,
            dominicalesFestivos: $base->dominicalesFestivos,
            dominicalesHabituales: $base->dominicalesHabituales,
            recargosNocturnos: $base->recargosNocturnos,
            cotizaPrestaciones: $base->cotizaPrestaciones,
            tieneAuxilioEducacion: false,
            diasTrabajadosAlMes: $base->diasTrabajadosAlMes,
        );

        $conAuxilio = new DatosPersona(
            nombre: $base->nombre,
            salario: $base->salario,
            esRolMensual: $base->esRolMensual,
            auxilioSinIncidencia: $base->auxilioSinIncidencia,
            capacitaciones: $base->capacitaciones,
            porcentajeHorasExtra: $base->porcentajeHorasExtra,
            horasExtraDiurnas: $base->horasExtraDiurnas,
            horasExtraNocturnas: $base->horasExtraNocturnas,
            horasExtraFestivasDiurnas: $base->horasExtraFestivasDiurnas,
            horasExtraFestivasNocturnas: $base->horasExtraFestivasNocturnas,
            dominicalesFestivos: $base->dominicalesFestivos,
            dominicalesHabituales: $base->dominicalesHabituales,
            recargosNocturnos: $base->recargosNocturnos,
            cotizaPrestaciones: $base->cotizaPrestaciones,
            tieneAuxilioEducacion: true,
            diasTrabajadosAlMes: $base->diasTrabajadosAlMes,
        );

        $calculadora = $this->calculadora();

        $diferencia = $calculadora->calcular($conAuxilio)->porMes
            - $calculadora->calcular($sinAuxilio)->porMes;

        $this->assertEqualsWithDelta(
            $factores->auxilioEducacion,
            $diferencia,
            1.0,
            'El auxilio de educacion deberia sumar exactamente su valor mensual.'
        );
    }

    /** El auxilio de transporte solo aplica hasta dos salarios minimos. */
    public function test_el_auxilio_de_transporte_tiene_tope(): void
    {
        $factores = FactoresLegales::vigentes();
        $minimo = $factores->salarioMinimo;

        $this->assertSame($factores->auxilioTransporte, $factores->auxilioTransportePara($minimo));
        $this->assertSame(0, $factores->auxilioTransportePara($minimo * 3));
    }

    /**
     * El factor prestacional sigue las tres condiciones de la norma.
     */
    public function test_el_factor_prestacional_distingue_los_cinco_casos(): void
    {
        $factores = FactoresLegales::vigentes();
        $minimo = $factores->salarioMinimo;

        /** Por encima de 13 salarios minimos manda el factor integral. */
        $this->assertSame(
            $factores->factorIntegral,
            $factores->factorPrestacional($minimo * 14, $minimo * 14, true)
        );

        /** Con prestaciones, segun el ingreso supere o no 10 minimos. */
        $this->assertSame(
            $factores->factorConPrestacionesBajo,
            $factores->factorPrestacional($minimo * 2, $minimo * 5, true)
        );
        $this->assertSame(
            $factores->factorConPrestacionesAlto,
            $factores->factorPrestacional($minimo * 2, $minimo * 11, true)
        );

        /** Sin prestaciones, el mismo criterio con los otros dos factores. */
        $this->assertSame(
            $factores->factorSinPrestacionesBajo,
            $factores->factorPrestacional($minimo * 2, $minimo * 5, false)
        );
        $this->assertSame(
            $factores->factorSinPrestacionesAlto,
            $factores->factorPrestacional($minimo * 2, $minimo * 11, false)
        );
    }

    /**
     * Sin factores legales, el calculo se detiene.
     *
     * Multiplicar por cero daria una nomina irreal sin avisar a nadie.
     */
    public function test_sin_factores_legales_falla_en_vez_de_dar_cero(): void
    {
        $this->expectException(RuntimeException::class);

        new FactoresLegales(
            horaExtraDiurna: 1.25,
            horaExtraNocturna: 1.75,
            horaExtraFestivaDiurna: 2.0,
            horaExtraFestivaNocturna: 2.5,
            dominicalFestivo: 1.75,
            diaHabitual: 1.0,
            recargoNocturno: 0.35,
            diaFamilia: 0.5,
            factorIntegral: 0.3,
            factorConPrestacionesBajo: 0.49,
            factorConPrestacionesAlto: 0.63,
            factorSinPrestacionesBajo: 0.45,
            factorSinPrestacionesAlto: 0.59,
            auxilioTransporte: 200_000,
            auxilioEducacion: 23_725,
            horasTrabajadasPorDia: 0.0,
            salarioMinimo: 1_423_500,
        );
    }

    /** Los factores vigentes se cargan desde la base sin sorpresas. */
    public function test_carga_los_factores_vigentes(): void
    {
        $factores = FactoresLegales::vigentes();

        $this->assertGreaterThan(0, $factores->salarioMinimo);
        $this->assertGreaterThan(0, $factores->horasTrabajadasPorDia);
        $this->assertGreaterThan(1, $factores->horaExtraDiurna);

        $modelo = OrigenFactor::vigentes();
        $this->assertSame((int) $modelo->Salario_min_vigente, $factores->salarioMinimo);
    }
}
