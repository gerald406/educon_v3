<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_section_id')->constrained('survey_sections')->onDelete('cascade');
            $table->text('question_text');
            $table->text('help_text')->nullable();
            $table->enum('question_type', [
                'text_short',
                'text_long',
                'numeric',
                'date',
                'yes_no',
                'true_false',
                'single_choice',
                'multiple_choice',
                'dropdown',
                'file_upload',
                'rating'
            ])->default('text_short');
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('validation_rules')->nullable();
            $table->string('default_value')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['survey_section_id', 'sort_order']);
            $table->index('question_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
