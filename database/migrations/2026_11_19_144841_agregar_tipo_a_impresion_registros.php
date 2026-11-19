<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue QUE salio: un Word, un PDF o un correo.
 *
 * Hasta aqui la tabla solo registraba la generacion del Word -el sistema
 * anterior lo llamaba "impresion"-, de modo que un correo enviado al
 * cliente no dejaba ningun rastro. Ante un cliente que discute una oferta
 * no es lo mismo "se genero un borrador" que "se le envio".
 *
 * Los 49 registros que ya existen se marcan como `word`, que es lo unico
 * que podian haber sido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impresion_registros', function (Blueprint $table): void {
            $table->string('tipo', 20)
                ->default('word')
                ->after('cotizacion_id');

            /**
             * A quien salio el correo, cuando fue un correo.
             *
             * `destinatario` guarda el nombre del contacto; esta columna, la
             * direccion a la que de verdad se mando. Sin ella no se puede
             * demostrar un envio.
             */
            $table->string('correo_destino')->nullable()->after('destinatario');

            /** Si el envio fallo, por que. Nulo cuando salio bien. */
            $table->string('fallo')->nullable()->after('referencia');

            $table->index(['cotizacion_id', 'fecha_hora']);
            $table->index('tipo');
        });

        /** Lo ya registrado solo podia ser el Word. */
        DB::table('impresion_registros')->update(['tipo' => 'word']);
    }

    public function down(): void
    {
        Schema::table('impresion_registros', function (Blueprint $table): void {
            $table->dropIndex(['cotizacion_id', 'fecha_hora']);
            $table->dropIndex(['tipo']);
            $table->dropColumn(['tipo', 'correo_destino', 'fallo']);
        });
    }
};
