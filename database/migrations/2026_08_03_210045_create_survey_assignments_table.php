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
            $table->morphs('assignable'); // Crea assignable_type (varchar) y assignable_id (unsignedBigInteger)
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->datetime('deadline')->nullable();
            $table->timestamps();

            // Índices para consultas rápidas
            $table->index('status');

            // Clave única para evitar asignaciones duplicadas de un mismo formulario al mismo modelo
            $table->unique(['survey_form_id', 'assignable_type', 'assignable_id'], 'unique_form_assignment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_assignments');
    }
};
