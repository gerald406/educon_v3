<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [NUEVO] Programa de estudios de adscripción del docente: es el "Programa de Estudios" que
// figura en el horario no lectivo y en la programación de actividades. Opcional; no limita
// en qué programas puede dictar (eso lo sigue definiendo la carga académica).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('career_id')->nullable()->after('institution_id')
                ->constrained('careers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('career_id');
        });
    }
};
