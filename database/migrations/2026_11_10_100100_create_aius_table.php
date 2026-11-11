<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parametros del AIU (Administracion, Imprevistos, Utilidad) de una cotizacion.
 *
 * Las dos banderas de texto deciden los cuatro escenarios de calculo:
 * - contrato_obra: en contrato de obra el IVA grava solo la utilidad; si no,
 *   grava el total.
 * - mostrar_aiu: con el AIU visible, A+I+U van desglosados aparte y los items
 *   muestran el costo directo; con el AIU oculto, el factor se aplica item por
 *   item y el total ya lo trae dentro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aius', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('contrato_obra', 2)->nullable();
            $table->string('mostrar_aiu', 2)->nullable();
            $table->string('contrato_actual', 2)->nullable();

            $table->decimal('administracion', 4, 2)->nullable();
            $table->decimal('imprevistos', 4, 2)->nullable();
            $table->decimal('utilidad', 4, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aius');
    }
};
