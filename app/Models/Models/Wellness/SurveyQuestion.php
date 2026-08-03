<?php

namespace App\Models\Wellness;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'survey_section_id',
        'question_text',
        'help_text',
        'question_type',
        'is_required',
        'sort_order',
        'validation_rules',
        'default_value',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_rules' => 'array',
    ];

    public function section()
    {
        return $this->belongsTo(SurveySection::class, 'survey_section_id');
    }

    public function options()
    {
        return $this->hasMany(SurveyQuestionOption::class)->orderBy('sort_order');
    }

    public function answers()
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    /* public function dependencies()
    {
        return $this->hasMany(SurveyQuestionDependency::class, 'depends_on_question_id');
    }

    public function dependents()
    {
        return $this->hasMany(SurveyQuestionDependency::class, 'survey_question_id');
    } */

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Método para verificar si la pregunta tiene opciones
    public function hasOptions(): bool
    {
        return in_array($this->question_type, ['single_choice', 'multiple_choice', 'dropdown']);
    }
}
