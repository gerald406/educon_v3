<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->enum('form_type', ['encuesta', 'cuestionario', 'ficha_socioeconomica', 'diagnostico', 'evaluacion', 'otro'])->default('encuesta');
            $table->boolean('is_active')->default(true);
            $table->datetime('starts_at')->nullable();
            $table->datetime('ends_at')->nullable();
            $table->boolean('allow_multiple_responses')->default(false);
            $table->boolean('is_mandatory')->default(false);
            $table->enum('target_audience', ['all', 'students', 'specific_careers', 'specific_periods'])->default('all');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
            $table->index('is_active');
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_forms');
    }
};
