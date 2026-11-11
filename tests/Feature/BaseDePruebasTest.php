<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\OrigenFactor;
use App\Models\PerAuxConvencional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La base de pruebas tiene todo lo que el calculo necesita.
 *
 * Nace de dos fallos reales de esta sesion:
 *
 * 1. Diez tablas quedaron fuera del esquema nuevo porque se midio su tamano
 *    con information_schema.table_rows, que es una estimacion del optimizador
 *    y para tablas pequenas devuelve 0. Entre ellas estaba origen_factors,
 *    sin la cual no se puede calcular una sola nomina.
 *
 * 2. El comando que prepara la base copiaba 0 filas y aun asi terminaba
 *    diciendo "lista". Un dato vacio no se nota hasta que un calculo da un
 *    resultado absurdo.
 */
class BaseDePruebasTest extends TestCase
{
    /** Las tablas que el calculo necesita existen en el esquema nuevo. */
    public function test_existen_las_tablas_que_el_calculo_necesita(): void
    {
        foreach ([
            'origen_factors',
            'per_aux_convencionales',
            'precargado_epps',
            'precargado_heramientas',
            'precargado_equipos',
            'precargado_consumibles',
            'precargado_item_epps',
            'precargado_item_heramientas',
            'precargado_item_equipos',
            'precargado_item_consumibles',
        ] as $tabla) {
            $this->assertTrue(
                Schema::hasTable($tabla),
                "Falta la tabla '{$tabla}' en el esquema nuevo."
            );
        }
    }

    /**
     * Los factores legales estan cargados y son utilizables.
     *
     * Sin esta fila, cada salario se multiplica por cero y toda cotizacion con
     * personal da un costo irreal sin mostrar ningun error.
     */
    public function test_los_factores_legales_estan_cargados(): void
    {
        $factores = OrigenFactor::vigentes();

        $this->assertNotNull($factores, 'No hay factores legales cargados: la nomina daria cero.');

        $this->assertGreaterThan(
            0,
            $factores->Salario_min_vigente,
            'El salario minimo debe ser mayor que cero: decide el auxilio de transporte.'
        );

        $this->assertGreaterThan(
            0,
            $factores->Pro_horas_trabajadasdia,
            'Las horas trabajadas por dia son un divisor en el calculo de recargos.'
        );

        /** Los recargos de ley son siempre mayores que uno. */
        foreach (['HEDO', 'HENO', 'HEDF', 'HENF'] as $recargo) {
            $this->assertGreaterThan(
                1,
                $factores->{$recargo},
                "El recargo {$recargo} deberia ser mayor que 1."
            );
        }
    }

    /**
     * Las tablas con datos reales llegaron completas.
     *
     * Comprueba contra la base de referencia, no contra numeros fijos, para
     * que siga valiendo cuando se refresque el respaldo.
     */
    public function test_los_datos_se_copiaron_completos(): void
    {
        $referencia = 'estimaweb31';

        foreach ([
            'cotizaciones',
            'revisions',
            'personals',
            'origen_factors',
            'turnos',
            'epps',
        ] as $tabla) {
            $enReferencia = (int) DB::selectOne(
                "SELECT COUNT(*) AS n FROM `{$referencia}`.`{$tabla}`"
            )->n;

            $enPruebas = (int) DB::table($tabla)->count();

            $this->assertSame(
                $enReferencia,
                $enPruebas,
                "La tabla '{$tabla}' llego incompleta: {$enPruebas} de {$enReferencia} filas. "
                . 'Ejecute: php artisan pruebas:preparar'
            );
        }
    }

    /** Las 48 cotizaciones reales estan disponibles para los golden masters. */
    public function test_las_cotizaciones_reales_estan_cargadas(): void
    {
        $this->assertSame(48, Cotizacion::query()->count());
    }

    /**
     * Los auxilios convencionales se leen correctamente.
     *
     * Su condicion decide el multiplicador, asi que un valor inesperado
     * cambiaria el costo de la persona.
     */
    public function test_las_condiciones_de_los_auxiliares_son_conocidas(): void
    {
        $condiciones = PerAuxConvencional::query()
            ->distinct()
            ->pluck('condicion')
            ->all();

        foreach ($condiciones as $condicion) {
            $this->assertContains(
                $condicion,
                ['Dias del mes', 'Dia Trabajado'],
                "Condicion de auxilio no prevista: '{$condicion}'."
            );
        }
    }

    /**
     * La base de pruebas no es la de referencia.
     *
     * Si lo fuera, cualquier prueba que escriba destruiria los datos contra
     * los que se compara todo.
     */
    public function test_las_pruebas_no_corren_sobre_la_base_de_referencia(): void
    {
        $base = DB::connection()->getDatabaseName();

        $this->assertNotSame(
            'estimaweb31',
            $base,
            'Las pruebas estan corriendo sobre la base de REFERENCIA.'
        );

        $this->assertSame('estimaweb31_test', $base);
    }
}
