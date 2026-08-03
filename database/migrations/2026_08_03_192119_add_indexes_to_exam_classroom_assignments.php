<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_classroom_assignments', function (Blueprint $table) {
            $table->index('exam_classroom_id');
            // 'applicant_id' ya es unique, pero también se puede indexar (ya lo hace la unique)
        });
    }
    public function down(): void
    {
        Schema::table('exam_classroom_assignments', function (Blueprint $table) {
            $table->dropIndex(['exam_classroom_id']);
        });
    }
};
