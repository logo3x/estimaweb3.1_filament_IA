<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Gerencias de la empresa. Agrupan usuarios y cotizaciones. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gerencias', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gerencias');
    }
};
