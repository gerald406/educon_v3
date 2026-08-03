<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->index('issued_at');
            $table->index('cash_session_id');
            $table->index('client_id');
        });
    }
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['issued_at']);
            $table->dropIndex(['cash_session_id']);
            $table->dropIndex(['client_id']);
        });
    }
};
