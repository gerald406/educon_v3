<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [NUEVO] Horario no lectivo de un docente en un período académico.
 */
class NonTeachingPlan extends Model
{
    protected $table = 'teacher_non_teaching_plans';

    protected $fillable = [
        'teacher_id',
        'academic_period_id',
        'workday_hours',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(NonTeachingActivity::class, 'teacher_non_teaching_plan_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(NonTeachingBlock::class, 'teacher_non_teaching_plan_id');
    }
}
