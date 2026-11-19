<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las peticiones que tardaron demasiado.
 *
 * **Solo las lentas.** Registrar todas llenaria la tabla de ruido y, en un
 * hosting compartido, la propia escritura costaria mas que lo que se
 * pretende medir. El umbral vive en el middleware.
 *
 * `consultas` es la columna que de verdad delata un problema: una pantalla
 * cuyo numero de consultas crece con los datos es un N+1, y eso no se ve
 * hasta que hay datos de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peticiones_lentas', function (Blueprint $table): void {
            $table->id();

            $table->string('metodo', 10);

            /** Las direcciones de Livewire son largas. */
            $table->string('ruta', 500);

            $table->unsignedInteger('milisegundos');
            $table->unsignedInteger('consultas')->default(0);
            $table->unsignedInteger('milisegundos_en_consultas')->default(0);
            $table->unsignedInteger('memoria_kb')->default(0);

            $table->unsignedSmallInteger('codigo')->nullable();

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            /** Livewire se separa: una recarga parcial no se compara con una pagina entera. */
            $table->boolean('es_livewire')->default(false);

            $table->timestamp('cuando')->useCurrent();

            $table->index('cuando');
            $table->index('milisegundos');
            $table->index(['ruta', 'cuando'], 'peticiones_lentas_ruta_cuando');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peticiones_lentas');
    }
};
