<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [NUEVO] Desglose de las horas semanales de la UD en teoría y práctica
// (columnas TEORÍA / PRÁCTICA del horario personal del docente). Opcionales.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('didactic_units', function (Blueprint $table) {
            $table->unsignedTinyInteger('theory_hours')->nullable()->after('weekly_hours');
            $table->unsignedTinyInteger('practice_hours')->nullable()->after('theory_hours');
        });
    }

    public function down(): void
    {
        Schema::table('didactic_units', function (Blueprint $table) {
            $table->dropColumn(['theory_hours', 'practice_hours']);
        });
    }
};
