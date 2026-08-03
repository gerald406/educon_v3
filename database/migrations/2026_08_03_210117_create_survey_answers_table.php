<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained('survey_responses')->onDelete('cascade');
            $table->foreignId('survey_question_id')->constrained('survey_questions')->onDelete('cascade');
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Índices
            $table->index(['survey_response_id', 'survey_question_id']);
            $table->index('survey_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
    }
};
