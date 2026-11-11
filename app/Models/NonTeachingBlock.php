<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [NUEVO] Bloque no lectivo ubicado en la semana (60 min, o 30 min para la media hora).
 */
class NonTeachingBlock extends Model
{
    protected $table = 'teacher_non_teaching_blocks';

    protected $fillable = [
        'teacher_non_teaching_plan_id',
        'teacher_non_teaching_activity_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => TimeCast::class,
        'end_time' => TimeCast::class,
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(NonTeachingPlan::class, 'teacher_non_teaching_plan_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(NonTeachingActivity::class, 'teacher_non_teaching_activity_id');
    }

    public function minutes(): int
    {
        return (int) abs($this->start_time->diffInMinutes($this->end_time));
    }
}
