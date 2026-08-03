<?php

namespace App\Models\Wellness;

use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\DidacticUnit;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_form_id',
        'student_id',
        'career_id',
        'study_plan_id',
        'academic_period_id',
        'module_id',
        'didactic_unit_id',
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

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function career()
    {
        return $this->belongsTo(Career::class);
    }

    public function studyPlan()
    {
        return $this->belongsTo(StudyPlan::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function didacticUnit()
    {
        return $this->belongsTo(DidacticUnit::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
