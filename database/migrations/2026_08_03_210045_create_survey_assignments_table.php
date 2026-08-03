<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_form_id')->constrained('survey_forms')->onDelete('cascade');
            $table->foreignId('student_id')->nullable()->constrained('students')->onDelete('cascade');
            $table->foreignId('career_id')->nullable()->constrained('careers')->onDelete('cascade');
            $table->foreignId('study_plan_id')->nullable()->constrained('study_plans')->onDelete('cascade');
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->onDelete('cascade');
            $table->foreignId('module_id')->nullable()->constrained('modules')->onDelete('cascade');
            $table->foreignId('didactic_unit_id')->nullable()->constrained('didactic_units')->onDelete('cascade');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->datetime('deadline')->nullable();
            $table->timestamps();

            // Índices para consultas rápidas
            $table->index('student_id');
            $table->index('career_id');
            $table->index('academic_period_id');
            $table->index('status');

            // Clave única para evitar asignaciones duplicadas (cuando se asigna a un estudiante específico)
            $table->index(['survey_form_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_assignments');
    }
};
