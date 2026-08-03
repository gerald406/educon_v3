<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->index(['academic_period_id', 'didactic_unit_id']);
            $table->index('teacher_id');
        });
    }
    public function down(): void
    {
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->dropIndex(['academic_period_id', 'didactic_unit_id']);
            $table->dropIndex(['teacher_id']);
        });
    }
};
