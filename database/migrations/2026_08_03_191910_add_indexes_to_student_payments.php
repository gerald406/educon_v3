<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_payments', function (Blueprint $table) {
            $table->index(['student_id', 'status']);
            $table->index('due_date');
        });
    }
    public function down(): void
    {
        Schema::table('student_payments', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'status']);
            $table->dropIndex(['due_date']);
        });
    }
};
