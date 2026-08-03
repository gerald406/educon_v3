<?php

namespace App\Models\Wellness;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_form_id',
        'student_id',
        'responded_at',
        'ip_address',
        'user_agent',
        'status',
        'is_final',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'is_final' => 'boolean',
    ];

    public function surveyForm()
    {
        return $this->belongsTo(SurveyForm::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function answers()
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    // Scopes
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }
}
