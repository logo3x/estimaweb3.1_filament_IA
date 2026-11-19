<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quien cambio que, cuando y desde donde.
 *
 * Hasta aqui el 3.1 solo dejaba rastro de los accesos (en ficheros, que en
 * cPanel nadie puede leer) y de las aprobaciones. Nadie podia saber quien
 * cambio un precio o un margen, que es justo lo que se pregunta cuando una
 * oferta sale con un numero que no cuadra.
 *
 * Se guarda el valor viejo y el nuevo, no solo que "hubo un cambio": sin el
 * valor anterior el registro no sirve para reconstruir que paso.
 *
 * `usuario_id` admite nulo a proposito -al contrario que en los envios-:
 * un cambio hecho por una tarea del cron no tiene sesion, y perder el
 * registro del cambio seria peor que no saber quien lo hizo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_de_cambios', function (Blueprint $table): void {
            $table->id();

            /** Sobre que se actuo: el modelo y su id. */
            $table->string('entidad', 60);
            $table->unsignedBigInteger('entidad_id');

            /** Que paso: creado, modificado, borrado, aprobado... */
            $table->string('accion', 30);

            /**
             * La cotizacion afectada, si la hay.
             *
             * Permite responder "que le paso a ESTA cotizacion" sin tener que
             * recorrer la tabla entera. Nulo para cambios que no van de una
             * cotizacion, como editar un catalogo o un usuario.
             */
            $table->unsignedBigInteger('cotizacion_id')->nullable();

            /**
             * Que cambio exactamente: campo, antes y despues.
             *
             * En JSON porque cada entidad tiene sus propios campos y no
             * caben en columnas fijas.
             */
            $table->json('cambios')->nullable();

            /** Un resumen legible, para no tener que interpretar el JSON. */
            $table->string('resumen', 500)->nullable();

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            /** Desde donde: ayuda a distinguir un acceso extraño. */
            $table->string('ip', 45)->nullable();

            $table->timestamp('cuando')->useCurrent();

            /** Las dos consultas que se van a hacer de verdad. */
            $table->index(['cotizacion_id', 'cuando']);
            $table->index(['entidad', 'entidad_id']);
            $table->index('cuando');
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_de_cambios');
    }
};
