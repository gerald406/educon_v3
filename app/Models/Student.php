<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Importaciones del módulo de Bienestar
use App\Models\Wellness\SurveyAssignment;
use App\Models\Wellness\SurveyResponse;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $with = ['user', 'career', 'studyPlan'];

    protected $fillable = [
        'user_id',
        'career_id',
        'study_plan_id',
        'code',
        'current_semester',
        'accumulated_credits',
        'weighted_average',
        'academic_status',
        'admission_date',
        'graduation_date',
        // Datos Demográficos
        'phone',
        'address',
        'gender',
        'birthday',
        'ubigeo_birth_id',
        'origin_school_id',
        'school_graduation_year',
        'photo_url'
    ];

    protected $casts = [
        'admission_date' => 'date',
        'graduation_date' => 'date',
        'birthday' => 'date',
        'weighted_average' => 'decimal:2',
    ];

    // ==========================================
    // RELACIONES ACADÉMICAS BÁSICAS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('academic_status', 'regular');
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    public function enrollmentReserves(): HasMany
    {
        return $this->hasMany(EnrollmentReserve::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    // ==========================================
    // RELACIONES DEMOGRÁFICAS (UBIGEO / COLEGIO)
    // ==========================================

    public function birthLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'ubigeo_birth_id', 'iddist');
    }

    public function originSchool(): BelongsTo
    {
        return $this->belongsTo(OriginSchool::class);
    }

    // ==========================================
    // RELACIONES: BIENESTAR ESTUDIANTIL
    // ==========================================

    public function surveyAssignments()
    {
        return $this->morphMany(SurveyAssignment::class, 'assignable');
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
