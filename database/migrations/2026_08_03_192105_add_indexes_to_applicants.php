<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->index('admission_offering_id');
            $table->index('application_status');
            $table->index('code'); // ya es unique, pero un índice adicional no duele
        });
    }
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropIndex(['admission_offering_id']);
            $table->dropIndex(['application_status']);
            $table->dropIndex(['code']);
        });
    }
};
