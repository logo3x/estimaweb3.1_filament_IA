<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factores legales de nomina y plantillas de recursos reutilizables.
 *
 * Estas tablas quedaron fuera de la primera tanda de migraciones por un error
 * de medicion: se consultaron los conteos de information_schema.table_rows,
 * que son una estimacion del optimizador y para tablas pequenas suele dar 0.
 * Diez de las trece tablas descartadas si tenian datos.
 *
 * origen_factors es ademas imprescindible: guarda los 16 factores con que se
 * calcula la nomina (salario minimo, auxilio de transporte, recargos
 * nocturnos, factor prestacional). Sin ella no se puede calcular el costo de
 * ninguna persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Factores legales vigentes. Es una sola fila que el area
         * administrativa actualiza cuando cambia la normativa (tipicamente en
         * enero, con el salario minimo).
         */
        Schema::create('origen_factors', function (Blueprint $table): void {
            $table->id();

            /**
             * Recargos por hora extra y trabajo nocturno o festivo.
             *
             * La precision importa: estos factores multiplican salarios, asi
             * que un decimal de menos se convierte en pesos de diferencia.
             * Se reproducen los tipos exactos de produccion.
             */
            $table->decimal('HEDO', 5, 3);
            $table->decimal('HENO', 5, 3);
            $table->decimal('HEDF', 5, 3);
            $table->decimal('HENF', 5, 3);
            $table->decimal('DYFO', 5, 3);
            $table->decimal('DH', 5, 3);
            $table->decimal('RN', 5, 3);
            $table->decimal('DIAF', 7, 5);

            /** Factor prestacional minimo integral. */
            $table->decimal('FPMI', 6, 4);

            /**
             * Factores prestacionales segun el personal supere o no los diez
             * salarios minimos, con y sin auxilio de transporte.
             */
            $table->decimal('FEME10', 6, 4);
            $table->decimal('FEMA10', 6, 4);
            $table->decimal('FNME10', 6, 4);
            $table->decimal('FNMA10', 6, 4);

            $table->integer('aux_trans');
            $table->integer('aux_edu');
            $table->decimal('Pro_horas_trabajadasdia', 7, 5);
            $table->integer('Salario_min_vigente');

            /** Topes minimos sugeridos para el AIU. */
            $table->decimal('min_administracion', 4, 2)->nullable();
            $table->decimal('min_imprevistos', 4, 2)->nullable();
            $table->decimal('min_utilidad', 4, 2)->nullable();

            $table->timestamps();
        });

        /** Auxilios de convencion colectiva por persona. */
        Schema::create('per_aux_convencionales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_personal')->nullable()->constrained('personals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('condicion');
            $table->string('nombre');
            $table->integer('valor');
            $table->timestamps();
        });

        /**
         * Plantillas de recursos: conjuntos guardados por un usuario para
         * reutilizarlos en cotizaciones nuevas sin volver a armarlos.
         */
        foreach ([
            'precargado_epps',
            'precargado_heramientas',
            'precargado_equipos',
            'precargado_consumibles',
        ] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('nombre');
                $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        /**
         * Contenido de cada plantilla.
         *
         * Los nombres de columna reproducen los de produccion, con sus
         * inconsistencias de singular y plural.
         */
        Schema::create('precargado_item_epps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_datos_epps')->nullable()->constrained('epps')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_precargado_epp')->nullable()->constrained('precargado_epps')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('precargado_item_heramientas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_datos_herramienta')->nullable()->constrained('datos_herramientas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_precargado_herramienta')->nullable()->constrained('precargado_heramientas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('precargado_item_equipos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_datos_equipo')->nullable()->constrained('datos_equipos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_precargado_equipo')->nullable()->constrained('precargado_equipos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('precargado_item_consumibles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_datos_consumible')->nullable()->constrained('datos_consumibles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_precargado_consumible')->nullable()->constrained('precargado_consumibles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'precargado_item_consumibles', 'precargado_item_equipos',
            'precargado_item_heramientas', 'precargado_item_epps',
            'precargado_consumibles', 'precargado_equipos',
            'precargado_heramientas', 'precargado_epps',
            'per_aux_convencionales', 'origen_factors',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
