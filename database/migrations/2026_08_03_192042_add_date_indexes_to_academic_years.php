<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->index('start_date');
            $table->index('end_date');
            $table->index('status');
        });
    }
    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropIndex(['start_date']);
            $table->dropIndex(['end_date']);
            $table->dropIndex(['status']);
        });
    }
};
