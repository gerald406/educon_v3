<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_form_id')->constrained('survey_forms')->onDelete('cascade');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_collapsible')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['survey_form_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_sections');
    }
};
