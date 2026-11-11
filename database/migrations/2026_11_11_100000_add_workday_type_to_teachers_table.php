<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [NUEVO] Condición laboral del docente: 'full_time' (40 h semanales) o 'part_time' (20 h).
// Es independiente del tipo de contrato. Los docentes existentes quedan a tiempo completo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('workday_type', 20)->default('full_time')->after('contract_type');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('workday_type');
        });
    }
};
