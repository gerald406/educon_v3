<?php

namespace App\Models\Wellness;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveySection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'survey_form_id',
        'name',
        'description',
        'sort_order',
        'is_collapsible',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_collapsible' => 'boolean',
    ];

    public function surveyForm()
    {
        return $this->belongsTo(SurveyForm::class);
    }

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sort_order');
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
