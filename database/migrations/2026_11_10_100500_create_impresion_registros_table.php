<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora de envios: quien genero el documento de una cotizacion, cuando y
 * para quien. Sirve de respaldo ante un cliente que discute una oferta.
 *
 * El usuario se protege con restrictOnDelete: borrar una cuenta no puede
 * dejar huerfano el registro de un envio ya realizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impresion_registros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();
            $table->dateTime('fecha_hora');
            $table->string('destinatario');
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('referencia');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impresion_registros');
    }
};
