<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_form_id')->constrained('survey_forms')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->onDelete('cascade');
            $table->datetime('responded_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['draft', 'submitted', 'archived'])->default('draft');
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            // Índices para consultas rápidas
            $table->index(['survey_form_id', 'student_id']);
            $table->index(['survey_form_id', 'academic_period_id']);
            $table->index('status');
            $table->index('responded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
