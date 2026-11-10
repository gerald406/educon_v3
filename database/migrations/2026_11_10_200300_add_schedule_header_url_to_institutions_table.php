<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [NUEVO] Imagen de encabezado para los horarios oficiales (Excel).
// Opcional: si falta se usa la que trae la plantilla.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('schedule_header_url')->nullable()->after('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('schedule_header_url');
        });
    }
};
