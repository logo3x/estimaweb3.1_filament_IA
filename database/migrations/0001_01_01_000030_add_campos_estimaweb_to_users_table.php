<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos propios de EstimaWeb sobre la tabla users de Laravel.
 *
 * OJO: la gerencia viaja duplicada, como clave foranea (id_gerencia) y como
 * texto (gerencia). Se conserva asi para que el esquema sea identico al de
 * produccion y ambas versiones puedan compartir base durante la validacion.
 * Unificarlas en una sola columna queda anotado para el rediseno de esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('id_gerencia')->nullable()->after('password')->constrained('gerencias')->nullOnDelete();
            $table->string('gerencia')->default('')->after('id_gerencia');
            $table->string('avatar_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['id_gerencia']);
            $table->dropColumn(['id_gerencia', 'gerencia', 'avatar_url']);
        });
    }
};
