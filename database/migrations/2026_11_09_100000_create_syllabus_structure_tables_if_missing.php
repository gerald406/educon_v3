<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [NUEVO] Migración base del módulo de sílabos.
 *
 * En producción las tablas syllabus_indicators, syllabus_units y learning_sessions,
 * y varias columnas de syllabi, se crearon fuera de las migraciones. Esta migración
 * las crea SOLO si no existen (instalaciones nuevas y tests). En producción no hace
 * nada, porque todo ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Columnas faltantes en syllabi ---
        $columns = [
            'sumilla' => fn (Blueprint $t) => $t->text('sumilla')->nullable(),
            'unit_competence' => fn (Blueprint $t) => $t->text('unit_competence')->nullable(),
            'course_capacity' => fn (Blueprint $t) => $t->text('course_capacity')->nullable(),
            'employability_competencies' => fn (Blueprint $t) => $t->json('employability_competencies')->nullable(),
            'methodology' => fn (Blueprint $t) => $t->text('methodology')->nullable(),
            'environments' => fn (Blueprint $t) => $t->text('environments')->nullable(),
            'resources' => fn (Blueprint $t) => $t->text('resources')->nullable(),
            'evaluation_system' => fn (Blueprint $t) => $t->text('evaluation_system')->nullable(),
            'web_sources' => fn (Blueprint $t) => $t->text('web_sources')->nullable(),
            'submitted_at' => fn (Blueprint $t) => $t->timestamp('submitted_at')->nullable(),
            'approved_at' => fn (Blueprint $t) => $t->timestamp('approved_at')->nullable(),
            'approved_by' => fn (Blueprint $t) => $t->unsignedBigInteger('approved_by')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('syllabi', $name)) {
                Schema::table('syllabi', fn (Blueprint $table) => $definition($table));
            }
        }

        // --- Estado de syllabi: la migración original no incluye 'submitted' ni 'rejected' ---
        if (DB::getDriverName() === 'mysql') {
            $type = DB::selectOne("SHOW COLUMNS FROM syllabi WHERE Field = 'status'")->Type ?? '';
            if (! str_contains($type, "'submitted'")) {
                DB::statement("ALTER TABLE syllabi MODIFY status ENUM('draft','pending_approval','submitted','approved','observed','rejected') NOT NULL DEFAULT 'draft'");
            }
        } elseif (DB::getDriverName() === 'sqlite') {
            // En SQLite el enum es un CHECK: se reemplaza por string
            Schema::table('syllabi', function (Blueprint $table) {
                $table->string('status', 20)->default('draft')->change();
            });
        }

        // --- Tablas hijas ---
        if (! Schema::hasTable('syllabus_indicators')) {
            Schema::create('syllabus_indicators', function (Blueprint $table) {
                $table->id();
                $table->foreignId('syllabus_id')->constrained('syllabi')->cascadeOnDelete();
                $table->text('description');
                $table->integer('sort_order')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('syllabus_units')) {
            Schema::create('syllabus_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('syllabus_indicator_id')->constrained('syllabus_indicators')->cascadeOnDelete();
                $table->integer('session_number');
                $table->string('week_range')->nullable();
                $table->date('execution_date')->nullable();
                $table->string('name');
                $table->text('content')->nullable();
                $table->text('learning_outcome')->nullable();
                $table->string('evaluation_instrument')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('learning_sessions')) {
            Schema::create('learning_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('syllabus_unit_id')->unique()->constrained('syllabus_units')->cascadeOnDelete();
                $table->string('transversal_competence')->nullable();
                $table->string('activity_type', 30)->default('teorico-practico');
                $table->json('sequence_activities')->nullable();
                $table->text('evaluation_criteria')->nullable();
                $table->string('evaluation_technique')->nullable();
                $table->string('evaluation_instrument')->nullable();
                $table->string('evaluation_moment')->nullable();
                $table->text('bibliography')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Intencionalmente vacío: no es posible saber qué existía antes de up().
     * Revertir aquí borraría tablas con datos reales en producción.
     */
    public function down(): void
    {
        //
    }
};
