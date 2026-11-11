<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items de la cotizacion: las lineas que ve el cliente en la oferta.
 *
 * Cada recurso se reparte entre los items a traves de la tabla revisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_cotizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();

            $table->unsignedSmallInteger('numero');
            $table->string('nombre');
            $table->string('unidad');
            $table->integer('cantidad');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_cotizaciones');
    }
};
