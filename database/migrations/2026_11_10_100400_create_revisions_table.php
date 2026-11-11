<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reparte cada recurso entre los items de la cotizacion.
 *
 * Cada fila dice: "de este recurso, esta cantidad corresponde a este item".
 * Es la tabla que conecta el costo con lo que ve el cliente, y por lo tanto
 * la entrada del calculo del AIU.
 *
 * Sobre las once columnas id_*: una fila usa exactamente una y deja diez en
 * NULL. Se verifico sobre los datos reales (345 de 345 filas apuntan a un
 * unico recurso), asi que esto es una relacion polimorfica escrita a mano.
 * Laravel la expresaria con dos columnas (recurso_type + recurso_id), y esa
 * es la causa de que Herramientas, Equipos y Consumibles sean tres archivos
 * casi identicos en la interfaz.
 *
 * Se conserva igual A PROPOSITO: mientras ambas versiones deban correr sobre
 * la misma base para compararse, el esquema no puede divergir. El rediseno
 * queda para una fase propia, con su migrador de datos y su verificacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cotizacion')->nullable()->constrained('cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();

            $table->decimal('cantidad', 19, 8);
            $table->string('unidad');

            $table->foreignId('id_personal')->nullable()->constrained('personals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_herramienta')->nullable()->constrained('herramientas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_equipo')->nullable()->constrained('equipos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_consumible')->nullable()->constrained('consumibles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_vehiculo')->nullable()->constrained('vehiculos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_oficina')->nullable()->constrained('oficinas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_aseo')->nullable()->constrained('aseos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_servicio')->nullable()->constrained('servicios')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_viatico')->nullable()->constrained('viaticos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_comunicacion')->nullable()->constrained('comunicaciones')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_otros')->nullable()->constrained('otros')->cascadeOnDelete()->cascadeOnUpdate();

            $table->foreignId('id_item_cotizacion')->nullable()->constrained('item_cotizaciones')->cascadeOnDelete()->cascadeOnUpdate();

            $table->timestamps();

            /** El calculo recorre las revisiones de una cotizacion agrupadas por item. */
            $table->index(['id_cotizacion', 'id_item_cotizacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
