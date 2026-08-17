<?php

namespace App\Models\Wellness;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_form_id',
        'assignable_type',
        'assignable_id',
        'assigned_by',
        'status',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    public function surveyForm()
    {
        return $this->belongsTo(SurveyForm::class);
    }

    /**
     * Relación Polimórfica: Permite asignar la encuesta a un Student, Career, AcademicPeriod, etc.
     */
    public function assignable()
    {
        return $this->morphTo();
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
