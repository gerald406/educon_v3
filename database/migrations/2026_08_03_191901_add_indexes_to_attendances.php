<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['registration_id', 'class_date']);
            $table->index('class_date');
        });
    }
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['registration_id', 'class_date']);
            $table->dropIndex(['class_date']);
        });
    }
};
