<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogos maestros, compartidos por todas las cotizaciones.
 *
 * A diferencia de las tablas de recursos, estos no cuelgan de una cotizacion:
 * son listas de referencia que el usuario mantiene una sola vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** Turnos de trabajo con sus factores de horas extra y recargos. */
        Schema::create('turnos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->decimal('d_trabajados', 5, 3);
            $table->decimal('d_descansados', 5, 3);
            $table->decimal('d_trabajadosmes', 5, 3);
            $table->decimal('hedo', 9, 3);
            $table->decimal('heno', 9, 3);
            $table->decimal('hedf', 9, 3);
            $table->decimal('henf', 9, 3);
            $table->decimal('donfesoca', 9, 3);
            $table->decimal('donhabitual', 9, 3);
            $table->decimal('rn', 9, 3);
            $table->timestamps();
        });

        /** Elementos de proteccion personal. */
        Schema::create('epps', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->string('grupo');
            $table->integer('precio');
            $table->timestamps();
        });

        Schema::create('examenes_vacunas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nombre');
            $table->string('grupo')->nullable();
            $table->integer('precio');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examenes_vacunas');
        Schema::dropIfExists('epps');
        Schema::dropIfExists('turnos');
    }
};
