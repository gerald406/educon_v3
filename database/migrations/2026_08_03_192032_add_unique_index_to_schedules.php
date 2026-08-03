<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primero verificar que no existan duplicados (opcional, pero recomendable)
        // Si existen, deberías limpiarlos antes de agregar la restricción.
        Schema::table('schedules', function (Blueprint $table) {
            $table->unique(['teacher_assignment_id', 'day_of_week', 'start_time', 'end_time'], 'unique_schedule_exact');
        });
    }
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique('unique_schedule_exact');
        });
    }
};
