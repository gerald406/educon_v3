<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [NUEVO] Actividad no lectiva elegida por el docente (una subactividad por actividad).
 * Las horas se guardan en minutos (múltiplos de 30).
 */
class NonTeachingActivity extends Model
{
    public const PREPARATION = 'class_preparation';

    /** Las ocho actividades oficiales (RSG 349-2017-MINEDU), en el orden de las plantillas. */
    public const ACTIVITIES = [
        'academic_design' => 'Diseño y desarrollo académico',
        'institutional_development' => 'Desarrollo institucional',
        'productive_sector' => 'Estrategias de articulación con el sector productivo',
        'research_innovation' => 'Investigación e innovación',
        self::PREPARATION => 'Preparación de clases',
        'student_support' => 'Seguimiento y servicios de apoyo al estudiante y egresado',
        'sefsrt' => 'S.E.F.S.R.T.',
        'other' => 'Otras actividades',
    ];

    protected $table = 'teacher_non_teaching_activities';

    protected $fillable = [
        'teacher_non_teaching_plan_id',
        'activity',
        'sub_activity',
        'weekly_minutes',
    ];

    protected $casts = [
        'weekly_minutes' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(NonTeachingPlan::class, 'teacher_non_teaching_plan_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(NonTeachingBlock::class, 'teacher_non_teaching_activity_id');
    }

    public function isPreparation(): bool
    {
        return $this->activity === self::PREPARATION;
    }

    public function label(): string
    {
        return self::ACTIVITIES[$this->activity] ?? $this->activity;
    }
}
