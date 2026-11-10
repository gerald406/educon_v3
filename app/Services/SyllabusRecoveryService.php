<?php

namespace App\Services;

use App\Models\Syllabus;
use App\Models\SyllabusUnit;
use Illuminate\Support\Facades\DB;

/**
 * [NUEVO] Semanas 17 y 18 del sílabo: "Reforzamiento y recuperación de capacidades no logradas".
 *
 * Estructura nueva: semanas 1-16 regulares (por indicador) + 17 y 18 de recuperación
 * con texto fijo e instrumento seleccionable. Se aplica a:
 *   - sílabos que ya la usan,
 *   - sílabos recién creados,
 *   - [MODIFICADO] sílabos editables (draft/observed) de cualquier periodo.
 * Los sílabos enviados o aprobados con la estructura anterior conservan sus 18 semanas
 * regulares: no se modifica un documento que ya está en revisión o aprobado.
 */
class SyllabusRecoveryService
{
    public function shouldApply(Syllabus $syllabus): bool
    {
        if ($syllabus->wasRecentlyCreated || $syllabus->usesRecoveryWeeks()) {
            return true;
        }

        return in_array($syllabus->status, ['draft', 'observed'], true);
    }

    /**
     * Crea (o convierte) las semanas 17 y 18 de recuperación y reimpone sus textos fijos.
     * Idempotente: conserva el instrumento elegido por el docente.
     * Si el sílabo tenía semanas regulares 17/18 (estructura anterior), se convierten.
     */
    public function ensureFor(Syllabus $syllabus): void
    {
        DB::transaction(function () use ($syllabus) {
            foreach (SyllabusUnit::RECOVERY_WEEKS as $week) {
                $unit = SyllabusUnit::recovery()
                    ->where('syllabus_id', $syllabus->id)
                    ->where('session_number', $week)
                    ->first();

                // Estructura anterior: semana regular con el mismo número → se convierte
                $unit ??= SyllabusUnit::whereHas('indicator', fn ($q) => $q->where('syllabus_id', $syllabus->id))
                    ->where('session_number', $week)
                    ->first();

                $unit ??= new SyllabusUnit;

                if (! in_array($unit->evaluation_instrument, SyllabusUnit::INSTRUMENTS, true)) {
                    $unit->evaluation_instrument = SyllabusUnit::DEFAULT_INSTRUMENT;
                }

                $unit->fill([
                    'type' => SyllabusUnit::TYPE_RECOVERY,
                    'syllabus_id' => $syllabus->id,
                    'syllabus_indicator_id' => null,
                    'session_number' => $week,
                    'name' => SyllabusUnit::RECOVERY_ACTIVITY,
                    'content' => SyllabusUnit::RECOVERY_CONTENT,
                    'learning_outcome' => SyllabusUnit::RECOVERY_LEARNING_OUTCOME,
                ])->save();
            }
        });

        $syllabus->unsetRelation('recoveryUnits');
    }
}
