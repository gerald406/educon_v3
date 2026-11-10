<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [NUEVO] Nombre corto del programa de estudios para las celdas de los horarios
// (ej. "Electricidad Ind."). Opcional: si falta se usa el nombre completo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('careers', function (Blueprint $table) {
            $table->string('short_name', 60)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('careers', function (Blueprint $table) {
            $table->dropColumn('short_name');
        });
    }
};
