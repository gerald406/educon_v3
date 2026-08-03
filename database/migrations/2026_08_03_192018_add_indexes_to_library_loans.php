<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_loans', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index('due_date');
        });
    }
    public function down(): void
    {
        Schema::table('library_loans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['due_date']);
        });
    }
};
