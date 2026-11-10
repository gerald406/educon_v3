<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    // Esta tabla no usa softDeletes

    protected $fillable = [
        'teacher_assignment_id',
        'classroom_resource_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];
    
    // [CORREGIDO] Con 'string' las horas llegaban como texto y ->format('H:i') fallaba
    // en la gestión de horarios, reportes, matrícula, asistencias y visor público.
    protected $casts = [
        'start_time' => TimeCast::class,
        'end_time' => TimeCast::class,
    ];

    /**
     * Un horario pertenece a una asignación (sección).
     */
    public function teacherAssignment()
    {
        return $this->belongsTo(TeacherAssignment::class);
    }

    /**
     * Un horario se dicta en un aula/recurso (opcional).
     */
    public function classroomResource()
    {
        return $this->belongsTo(ClassroomResource::class);
    }
}
