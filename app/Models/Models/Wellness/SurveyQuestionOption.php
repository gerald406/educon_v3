<?php

namespace App\Models\Wellness;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyQuestionOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'survey_question_id',
        'option_value',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    public function question()
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
