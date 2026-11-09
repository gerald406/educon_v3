<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyllabusUnit extends Model
{
    use HasFactory;

    // ============================================
    // [NUEVO] REGLAS DE PROGRAMACIÓN DE SEMANAS
    // ============================================

    /** Semanas regulares en sílabos con estructura nueva (1 a 16). */
    public const MAX_REGULAR_WEEKS = 16;

    /** Semanas regulares en sílabos anteriores a la estructura nueva. */
    public const LEGACY_MAX_REGULAR_WEEKS = 18;

    public const TYPE_REGULAR = 'regular';

    public const TYPE_RECOVERY = 'recovery';

    /** Semanas del apartado de reforzamiento y recuperación. */
    public const RECOVERY_WEEKS = [17, 18];

    public const RECOVERY_SECTION_TITLE = 'Reforzamiento y recuperación de capacidades no logradas';

    // Textos fijos e iguales para todos los sílabos (semanas 17 y 18)
    public const RECOVERY_ACTIVITY = 'Reforzamiento y evaluación de los indicadores no logrados';

    public const RECOVERY_CONTENT = 'Actividades de recuperación para estudiantes con indicadores pendientes. Entrega de productos o evaluaciones de recuperación según reglamento institucional. Cierre del periodo y consolidación de notas.';

    public const RECOVERY_LEARNING_OUTCOME = 'Cumple con las actividades de recuperación necesarias para la aprobación de la unidad didáctica, de acuerdo al reglamento.';

    /** Instrumentos de evaluación seleccionables (semanas regulares y de recuperación). */
    public const INSTRUMENTS = [
        'Lista de Cotejo',
        'Rúbrica',
        'Prueba Escrita',
        'Guía de Observación',
        'Portafolio',
        'Informe',
        'Otros',
    ];

    public const DEFAULT_INSTRUMENT = 'Lista de Cotejo';

    protected $fillable = [
        'type',          // [NUEVO]
        'syllabus_id',   // [NUEVO] solo en semanas de recuperación
        'syllabus_indicator_id',
        'session_number',
        'week_range',
        'execution_date',
        'name',
        'content',
        'learning_outcome',
        'evaluation_instrument',
    ];

    protected $casts = [
        'execution_date' => 'date',
    ];

    public function indicator()
    {
        return $this->belongsTo(SyllabusIndicator::class, 'syllabus_indicator_id');
    }

    /**
     * [NUEVO] Sílabo al que pertenece directamente (solo semanas de recuperación).
     */
    public function syllabus()
    {
        return $this->belongsTo(Syllabus::class);
    }

    /**
     * Relación 1 a 1 con el detalle operativo (Sesión de Aprendizaje)
     */
    public function learningSession()
    {
        return $this->hasOne(LearningSession::class);
    }

    // [NUEVO]
    public function isRecovery(): bool
    {
        return $this->type === self::TYPE_RECOVERY;
    }

    public function scopeRecovery(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_RECOVERY);
    }

    /**
     * [NUEVO] Todas las semanas de un sílabo: regulares (vía indicador) y de recuperación (vía syllabus_id).
     */
    public function scopeOfSyllabus(Builder $query, int $syllabusId): Builder
    {
        return $query->where(function (Builder $q) use ($syllabusId) {
            $q->whereHas('indicator', fn (Builder $i) => $i->where('syllabus_id', $syllabusId))
                ->orWhere('syllabus_id', $syllabusId);
        });
    }
}
