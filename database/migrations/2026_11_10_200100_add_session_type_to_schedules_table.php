<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [NUEVO] Tipo de cada bloque horario: 'T' (teoría) o 'P' (práctica). Opcional:
// los bloques existentes quedan sin tipo y se imprimen sin la marca (T)/(P).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('session_type', 1)->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('session_type');
        });
    }
};
