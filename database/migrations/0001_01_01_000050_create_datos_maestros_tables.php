<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogos de precios de referencia, alimentados por el area tecnica.
 *
 * Las cuatro tablas comparten estructura (codigo, nombre, grupo, precio);
 * comunicaciones es la excepcion: cobra por dia o por mes segun la unidad
 * elegida en la cotizacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['datos_herramientas', 'datos_equipos', 'datos_consumibles'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('codigo');
                $table->string('nombre');
                $table->string('grupo');
                $table->integer('precio');
                $table->timestamps();
            });
        }

        Schema::create('datos_comunicaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->string('grupo');
            $table->integer('precio_dia');
            $table->integer('precio_mes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_comunicaciones');
        Schema::dropIfExists('datos_consumibles');
        Schema::dropIfExists('datos_equipos');
        Schema::dropIfExists('datos_herramientas');
    }
};
