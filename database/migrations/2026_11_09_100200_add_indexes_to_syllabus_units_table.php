<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [NUEVO] Evita semanas de recuperación duplicadas por sílabo.
 * Las filas regulares tienen syllabus_id NULL, por lo que el índice no las restringe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('syllabus_units', function (Blueprint $table) {
            $table->unique(['syllabus_id', 'session_number'], 'syllabus_units_recovery_week_unique');
        });
    }

    public function down(): void
    {
        Schema::table('syllabus_units', function (Blueprint $table) {
            $table->dropUnique('syllabus_units_recovery_week_unique');
        });
    }
};
