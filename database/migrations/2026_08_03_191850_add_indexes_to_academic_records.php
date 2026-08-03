<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_records', function (Blueprint $table) {
            $table->index(['student_id', 'academic_period_id']);
            $table->index('didactic_unit_id');
        });
    }
    public function down(): void
    {
        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'academic_period_id']);
            $table->dropIndex(['didactic_unit_id']);
        });
    }
};
