<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [NUEVO] Semanas de "Reforzamiento y recuperación de capacidades no logradas" (17 y 18).
 *
 * - type: 'regular' (semanas 1-16, cuelgan de un indicador) | 'recovery' (semanas 17-18).
 * - syllabus_id: las semanas de recuperación cuelgan directamente del sílabo, no de un indicador.
 * - syllabus_indicator_id pasa a nullable (NULL solo en filas de recuperación).
 *
 * Aditiva: las filas existentes quedan como 'regular' y no se modifican.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('syllabus_units', function (Blueprint $table) {
            $table->string('type', 20)->default('regular')->after('id');
            $table->foreignId('syllabus_id')->nullable()->after('type')
                ->constrained('syllabi')->cascadeOnDelete();
        });

        Schema::table('syllabus_units', function (Blueprint $table) {
            $table->unsignedBigInteger('syllabus_indicator_id')->nullable()->change();
        });
    }

    /**
     * Revertir exige que syllabus_indicator_id vuelva a ser NOT NULL, por lo que
     * primero se eliminan las semanas de recuperación (texto fijo generado por el sistema;
     * sus sesiones de aprendizaje se eliminan en cascada).
     */
    public function down(): void
    {
        DB::table('syllabus_units')->where('type', 'recovery')->delete();

        Schema::table('syllabus_units', function (Blueprint $table) {
            $table->unsignedBigInteger('syllabus_indicator_id')->nullable(false)->change();
        });

        Schema::table('syllabus_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('syllabus_id');
            $table->dropColumn('type');
        });
    }
};
