<?php

namespace App\Models\Wellness;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'description',
        'form_type',
        'is_active',
        'starts_at',
        'ends_at',
        'allow_multiple_responses',
        'is_mandatory',
        'target_audience',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_multiple_responses' => 'boolean',
        'is_mandatory' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function sections()
    {
        return $this->hasMany(SurveySection::class);
    }

    public function assignments()
    {
        return $this->hasMany(SurveyAssignment::class);
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }
}
