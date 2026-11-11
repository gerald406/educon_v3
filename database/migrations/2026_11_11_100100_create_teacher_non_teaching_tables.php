<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [NUEVO] Horario no lectivo del docente por período académico:
//   plan (docente + período, con la jornada vigente al guardar)
//   → actividades (máx. 4, una subactividad cada una, horas en minutos)
//   → bloques ubicados en la semana (60 min, o 30 min para la media hora).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_non_teaching_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
            // Jornada semanal (40 o 20) vigente la última vez que se guardó en el período
            $table->unsignedTinyInteger('workday_hours')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'academic_period_id'], 'tnt_plans_teacher_period_unique');
        });

        Schema::create('teacher_non_teaching_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_non_teaching_plan_id');
            $table->string('activity', 40); // clave de NonTeachingActivity::ACTIVITIES
            $table->string('sub_activity', 255)->nullable();
            $table->unsignedSmallInteger('weekly_minutes')->default(0);
            $table->timestamps();

            $table->foreign('teacher_non_teaching_plan_id', 'tnt_activities_plan_fk')
                ->references('id')->on('teacher_non_teaching_plans')->cascadeOnDelete();
            $table->unique(['teacher_non_teaching_plan_id', 'activity'], 'tnt_activities_plan_activity_unique');
        });

        Schema::create('teacher_non_teaching_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_non_teaching_plan_id');
            $table->unsignedBigInteger('teacher_non_teaching_activity_id');
            $table->string('day_of_week', 10);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->foreign('teacher_non_teaching_plan_id', 'tnt_blocks_plan_fk')
                ->references('id')->on('teacher_non_teaching_plans')->cascadeOnDelete();
            $table->foreign('teacher_non_teaching_activity_id', 'tnt_blocks_activity_fk')
                ->references('id')->on('teacher_non_teaching_activities')->cascadeOnDelete();
            $table->unique(['teacher_non_teaching_plan_id', 'day_of_week', 'start_time'], 'tnt_blocks_plan_day_start_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_non_teaching_blocks');
        Schema::dropIfExists('teacher_non_teaching_activities');
        Schema::dropIfExists('teacher_non_teaching_plans');
    }
};
