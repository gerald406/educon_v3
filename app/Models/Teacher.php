<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
// (Asegúrate de que no haya NINGUNA importación 'use' 
// a 'ScheduleManager' o cualquier otro componente de Livewire)

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    // protected $with = ['teacher.user', 'didacticUnit', 'academicPeriod'];
    protected $with = ['user'];

    protected $fillable = [
        'user_id',
        'institution_id',
        'code',
        'academic_degree',
        'specialty',
        'professional_experience',
        'cv_url',
        'contract_type',
        'workday_type', // [NUEVO] 'full_time' (40 h) | 'part_time' (20 h)
        'career_id',    // [NUEVO] Programa de estudios de adscripción (documentos oficiales)
        'hire_date',
        'preparation_day',
        'status',
    ];

    /**
     * Un perfil de docente pertenece a un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * [NUEVO] Programa de estudios al que está adscrito el docente (opcional).
     */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /**
     * Un docente pertenece a una institución.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}