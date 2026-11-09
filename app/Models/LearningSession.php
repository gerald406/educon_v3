<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'syllabus_unit_id',
        'transversal_competence',
        'activity_type',
        'sequence_activities', // JSON Clave
        'evaluation_criteria',
        'evaluation_technique',
        'evaluation_instrument',
        'evaluation_moment',
        'bibliography',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'sequence_activities' => 'array', // Convierte el JSON de momentos en Array
        'completed_at' => 'datetime',
    ];

    public function syllabusUnit()
    {
        return $this->belongsTo(SyllabusUnit::class);
    }

    /**
     * [NUEVO] Valores iniciales de una sesión recién generada
     * (plantilla de secuencia didáctica: inicio, desarrollo y cierre).
     */
    public static function defaultAttributes(): array
    {
        return [
            'status'                 => 'pending',
            'activity_type'          => 'teorico-practico',
            'transversal_competence' => null,
            'sequence_activities'    => self::defaultSequence(),
        ];
    }

    public static function defaultSequence(): array
    {
        return [
            [
                'moment'    => 'inicio',
                'label'     => 'Inicio',
                'hint'      => 'Motivación, Recojo de saberes previos, Conflicto cognitivo',
                'activity'  => '',
                'resources' => '',
                'time'      => 20,
            ],
            [
                'moment'    => 'desarrollo',
                'label'     => 'Desarrollo',
                'hint'      => 'Construcción del aprendizaje, Aplicación',
                'activity'  => '',
                'resources' => '',
                'time'      => 60,
            ],
            [
                'moment'    => 'cierre',
                'label'     => 'Cierre',
                'hint'      => 'Metacognición y evaluación',
                'activity'  => '',
                'resources' => '',
                'time'      => 10,
            ],
        ];
    }

    /**
     * [NUEVO] Indica si la unidad (semana) pertenece al sílabo dado.
     * Evita que se acceda a sesiones de otro sílabo cambiando el id en la URL.
     */
    public static function unitBelongsToSyllabus(SyllabusUnit $unit, Syllabus $syllabus): bool
    {
        // [MODIFICADO] Incluye las semanas de recuperación (cuelgan del sílabo, no de un indicador)
        return SyllabusUnit::ofSyllabus($syllabus->id)
            ->whereKey($unit->id)
            ->exists();
    }
}
