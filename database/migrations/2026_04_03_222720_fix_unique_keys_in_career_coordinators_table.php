<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_coordinators', function (Blueprint $table) {
            // 1. Primero soltamos las llaves foráneas para que MySQL libere los índices
            $table->dropForeign(['user_id']);
            $table->dropForeign(['career_id']);

            // 2. Ahora sí podemos eliminar las restricciones únicas de forma segura
            $table->dropUnique(['user_id']);
            $table->dropUnique(['career_id']);

            // 3. Volvemos a crear las llaves foráneas normales (sin restricción única 1 a 1)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('career_id')->references('id')->on('careers')->onDelete('cascade');

            // 4. Creamos nuestro índice compuesto para evitar duplicados exactos
            $table->unique(['user_id', 'career_id'], 'user_career_unique');
        });
    }

    public function down(): void
    {
        Schema::table('career_coordinators', function (Blueprint $table) {
            // Soltamos foráneas
            $table->dropForeign(['user_id']);
            $table->dropForeign(['career_id']);

            // Soltamos el índice compuesto
            $table->dropUnique('user_career_unique');

            // Restauramos los únicos originales
            $table->unique('user_id');
            $table->unique('career_id');

            // Restauramos las foráneas
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('career_id')->references('id')->on('careers')->onDelete('cascade');
        });
    }
};
