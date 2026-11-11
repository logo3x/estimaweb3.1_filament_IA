<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las once categorias de recurso que componen el costo de una cotizacion.
 *
 * Todas cuelgan de la cotizacion y se borran con ella. Se agrupan en esta
 * migracion porque comparten ese ciclo de vida y porque la tabla revisions,
 * que las reparte entre los items, necesita que las once existan antes.
 *
 * Los nombres de columna reproducen los de produccion, incluidas sus
 * inconsistencias (id_consumibles en plural frente a id_equipo en singular).
 * Normalizarlos exigiria migrar datos, y el objetivo de esta etapa es que
 * ambas versiones puedan correr sobre la misma base.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** Personal: el recurso mas complejo, con nomina, turnos y recargos. */
        Schema::create('personals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_turno')->nullable()->constrained('turnos')->nullOnDelete();

            $table->string('nombre');
            $table->integer('salario');
            $table->integer('auxiliosin');
            $table->integer('capacitaciones');
            $table->integer('p_he');

            /** Horas extra y recargos, heredados del turno pero ajustables por fila. */
            $table->decimal('hedo', 9, 3);
            $table->decimal('heno', 9, 3);
            $table->decimal('hedf', 9, 3);
            $table->decimal('henf', 9, 3);
            $table->decimal('donfesoca', 9, 3);
            $table->decimal('donhabitual', 9, 3);
            $table->decimal('rn', 9, 3);

            $table->string('f_prestacional');
            /** Bandera SI/NO, no un texto libre: en produccion es varchar(3). */
            $table->string('aux_educacion', 3);
            $table->string('rol');

            $table->timestamps();
        });

        /** Auxilios y bonificaciones adicionales por persona. */
        Schema::create('per_aux_otros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_personal')->nullable()->constrained('personals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('condicion');
            $table->string('nombre');
            $table->integer('valor');
            $table->timestamps();
        });

        Schema::create('personal_epps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('personal_id')->constrained('personals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('epps_id')->constrained('epps')->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('cantidad');
            $table->integer('duracion_servicio');
            $table->timestamps();
        });

        Schema::create('personal_examenes_vacunas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('personal_id')->constrained('personals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('exavacu_id')->constrained('examenes_vacunas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('cantidad');
            $table->integer('duracion_servicio');
            $table->timestamps();
        });

        /**
         * Herramientas, equipos y consumibles: misma estructura exacta.
         * Cada uno agrupa items tomados del catalogo correspondiente.
         *
         * Los tres decimales son decimal(4,2): factores y periodos, no pesos,
         * con tope en 99,99. Se verifico sobre los datos reales que ninguno se
         * acerca al tope (maximos 45, 85 y 24), asi que hoy no trunca nada,
         * pero el margen es estrecho y queda anotado para el rediseno.
         *
         * En consumibles las tres columnas son nullable y en los otros dos no;
         * es asi en produccion y se respeta para que la importacion no falle.
         */
        foreach (['herramientas', 'equipos'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();
                $table->string('nombre');
                $table->decimal('periodo_evaluacion', 4, 2);
                $table->decimal('vrepo_anual', 4, 2);
                $table->decimal('vcalibracion_anual', 4, 2);
                $table->timestamps();
            });
        }

        Schema::create('consumibles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('nombre');
            $table->decimal('periodo_evaluacion', 4, 2)->nullable();
            $table->decimal('vrepo_anual', 4, 2)->nullable();
            $table->decimal('vcalibracion_anual', 4, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('item_herramientas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_herramienta')->nullable()->constrained('herramientas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_datos_herramientas')->nullable()->constrained('datos_herramientas')->nullOnDelete();
            $table->string('unidad');
            $table->integer('cantidad');
            $table->timestamps();
        });

        Schema::create('item_equipos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_equipo')->nullable()->constrained('equipos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_datos_equipo')->nullable()->constrained('datos_equipos')->nullOnDelete();
            $table->string('unidad');
            $table->integer('cantidad');
            $table->timestamps();
        });

        Schema::create('item_consumibles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_consumibles')->nullable()->constrained('consumibles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_datos_consumibles')->nullable()->constrained('datos_consumibles')->nullOnDelete();
            $table->string('unidad');
            $table->integer('cantidad');
            $table->timestamps();
        });

        /**
         * Vehiculos y oficinas llevan ademas un costo de movilizacion.
         *
         * movilizacion esta vacia en el 100% de los datos reales (32 de 32
         * vehiculos y 3 de 3 oficinas), asi que es nullable: declararla
         * obligatoria haria fallar la importacion de todos los registros.
         * Candidata a eliminarse si el inventario confirma que nadie la usa.
         */
        foreach (['vehiculos', 'oficinas'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();
                $table->string('nombre');
                $table->string('unidad');
                $table->integer('cantidad');
                $table->integer('movilizacion')->nullable();
                $table->timestamps();
            });
        }

        /** Recursos simples: nombre, unidad y cantidad. */
        foreach (['aseos', 'viaticos', 'servicios', 'otros'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();
                $table->string('nombre');
                $table->string('unidad');
                $table->integer('cantidad');
                $table->timestamps();
            });
        }

        /** Comunicaciones referencia su catalogo para saber si cobra por dia o por mes. */
        Schema::create('comunicaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_datos_comunicaciones')->nullable()->constrained('datos_comunicaciones')->nullOnDelete();
            $table->string('nombre');
            $table->string('unidad');
            $table->integer('cantidad');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'comunicaciones', 'otros', 'servicios', 'viaticos', 'aseos',
            'oficinas', 'vehiculos',
            'item_consumibles', 'item_equipos', 'item_herramientas',
            'consumibles', 'equipos', 'herramientas',
            'personal_examenes_vacunas', 'personal_epps', 'per_aux_otros', 'personals',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
