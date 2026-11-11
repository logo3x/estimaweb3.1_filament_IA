<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla raiz del sistema: una cotizacion.
 *
 * El esquema reproduce el del sistema en produccion para poder correr ambas
 * versiones sobre los mismos datos y exigir resultados identicos. La unica
 * diferencia deliberada es el charset: produccion quedo en utf8mb3, que no
 * admite emojis ni parte del rango Unicode; aqui se usa utf8mb4, que lo
 * contiene por completo, asi que ningun dato existente se pierde al importar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table): void {
            $table->id();

            $table->string('nombre');
            $table->string('codigo');
            $table->string('cliente');
            $table->string('contacto')->nullable();
            $table->string('cargo');
            $table->string('referencia');
            $table->text('alcance');
            $table->text('observaciones');
            $table->string('vigencia');
            $table->string('forma_pago');
            $table->string('otras_condiciones')->nullable();
            $table->string('gerencia')->nullable();

            /**
             * Flujo de aprobacion en dos niveles (tecnico y financiero).
             * Editar y aprobar son permisos separados: el autor de un borrador
             * puede editarlo aunque no tenga el rol que lo aprueba.
             */
            $table->string('aprobacion1', 20)->nullable();
            $table->foreignId('aprobacion1_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobacion1_fecha')->nullable();
            $table->text('aprobacion1_comentarios')->nullable();

            $table->string('aprobacion2', 20)->nullable();
            $table->foreignId('aprobacion2_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobacion2_fecha')->nullable();
            $table->text('aprobacion2_comentarios')->nullable();

            $table->string('estado')->nullable();

            $table->integer('dias_dura_servicio');
            $table->date('estimado_inicio');

            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();

            /**
             * Totales calculados. Son un cache de presentacion: la pantalla
             * recalcula en vivo, asi que estas columnas pueden quedar desfasadas
             * frente al codigo actual sin afectar lo que ve el usuario.
             */
            $table->decimal('valor_total', 15, 2)->nullable();
            $table->decimal('valor_total_con_aiu', 15, 2)->nullable();
            $table->decimal('aiu_administracion', 15, 2)->nullable();
            $table->decimal('aiu_imprevistos', 15, 2)->nullable();
            $table->decimal('aiu_utilidad', 15, 2)->nullable();
            $table->decimal('aiu_iva', 15, 2)->nullable();
            $table->decimal('aiu_valortotal_con_iva', 15, 2)->nullable();

            $table->timestamps();

            $table->index('gerencia');
            $table->index('estado');
            $table->index('codigo');
            $table->index('created_at');
            $table->index('updated_at');
            $table->index(['gerencia', 'created_at']);
            $table->index(['gerencia', 'updated_at']);
            $table->index(['id_user', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
