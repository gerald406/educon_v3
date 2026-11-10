<?php

namespace App\Services;

use App\Casts\TimeCast;
use App\Models\ClassroomResource;
use App\Models\Schedule;
use App\Models\TeacherAssignment;
use Carbon\Carbon;

/**
 * [NUEVO] Reglas de los bloques horarios de una sección (TeacherAssignment):
 *   1. aula disponible,
 *   2. dentro del horario del turno de la sección,
 *   3. sin superar las horas semanales de la UD (por sección), contadas en horas pedagógicas
 *      del turno (45 min mañana, 40 min noche; ver PedagogicalHourService),
 *   4. sin cruces de sección, docente (cualquier programa), grupo de estudiantes ni aula,
 *      solo en el período de la sección y con asignaciones activas.
 *
 * Turno sin horas o UD sin horas semanales no bloquean: se informan con warnings().
 */
class ScheduleValidationService
{
    // [MODIFICADO] No se dicta clase los sábados: solo se programa de lunes a viernes.
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    public function __construct(private PedagogicalHourService $pedagogicalHours) {}

    /**
     * Devuelve el primer motivo por el que el bloque no es válido, o null si es válido.
     * $start y $end en formato H:i.
     */
    public function validate(
        TeacherAssignment $assignment,
        string $day,
        string $start,
        string $end,
        ?int $classroomId,
        ?int $ignoreScheduleId = null
    ): ?string {
        if ($classroomId && ! ClassroomResource::whereKey($classroomId)->where('status', 'available')->exists()) {
            return 'El aula seleccionada no está disponible.';
        }

        if ($this->isWithinShift($assignment, $start, $end) === false) {
            $shift = $assignment->shift;

            return "El turno {$shift->name} es de {$shift->start_time->format('H:i')} a {$shift->end_time->format('H:i')}.";
        }

        $remaining = $this->remainingMinutes($assignment, $ignoreScheduleId);
        if ($remaining !== null && $this->blockMinutes($assignment, $start, $end) > $remaining) {
            $perHour = $this->minutesPerHour($assignment);

            return sprintf(
                'La UD tiene %s h semanales; ya hay %s h programadas, quedan %s h.',
                $this->formatHours($this->weeklyMinutes($assignment), $perHour),
                $this->formatHours($this->scheduledMinutes($assignment, $ignoreScheduleId), $perHour),
                $this->formatHours(max(0, $remaining), $perHour)
            );
        }

        return $this->findConflict(
            $assignment,
            $day,
            TimeCast::normalize($start),
            TimeCast::normalize($end),
            $classroomId,
            $ignoreScheduleId
        );
    }

    /** Minutos ya programados en la sección (sin contar el bloque en edición). */
    public function scheduledMinutes(TeacherAssignment $assignment, ?int $ignoreScheduleId = null): int
    {
        return (int) $assignment->schedules()
            ->when($ignoreScheduleId, fn ($q) => $q->whereKeyNot($ignoreScheduleId))
            ->get()
            ->sum(fn (Schedule $s) => $this->blockMinutes($assignment, $s->start_time->format('H:i'), $s->end_time->format('H:i')));
    }

    /** [NUEVO] Minutos de la hora pedagógica del turno de la sección (45, 40 o 60). */
    public function minutesPerHour(TeacherAssignment $assignment): int
    {
        return $this->pedagogicalHours->minutesPerHour($assignment->shift);
    }

    /** [NUEVO] Horas semanales de la UD expresadas en minutos del turno de la sección. */
    public function weeklyMinutes(TeacherAssignment $assignment): int
    {
        return (int) round($this->weeklyHours($assignment) * $this->minutesPerHour($assignment));
    }

    /**
     * [NUEVO] Minutos lectivos de un bloque. En un turno con franjas oficiales cuenta las
     * franjas que toca (el receso no suma); fuera de ellas, los minutos de reloj.
     */
    public function blockMinutes(TeacherAssignment $assignment, string $start, string $end): int
    {
        $group = $this->pedagogicalHours->groupOfShift($assignment->shift);

        if ($group) {
            $slots = array_filter(
                $this->pedagogicalHours->slotsTouching($start, $end),
                fn (array $slot) => $slot['group'] === $group
            );
            if ($slots !== []) {
                return count($slots) * $this->minutesPerHour($assignment);
            }
        }

        return $this->minutesBetween($start, $end);
    }

    /** Minutos que faltan para completar las horas semanales; null si la UD no tiene horas semanales. */
    public function remainingMinutes(TeacherAssignment $assignment, ?int $ignoreScheduleId = null): ?int
    {
        $weekly = $this->weeklyHours($assignment);
        if ($weekly <= 0) {
            return null;
        }

        return $this->weeklyMinutes($assignment) - $this->scheduledMinutes($assignment, $ignoreScheduleId);
    }

    /** true/false según el turno de la sección; null si la sección no tiene turno con horario. */
    public function isWithinShift(TeacherAssignment $assignment, string $start, string $end): ?bool
    {
        $shift = $assignment->shift;
        if (! $shift || ! $shift->start_time || ! $shift->end_time) {
            return null;
        }

        return TimeCast::normalize($start) >= $shift->start_time->format('H:i:s')
            && TimeCast::normalize($end) <= $shift->end_time->format('H:i:s');
    }

    /** Avisos que no bloquean el guardado. */
    public function warnings(TeacherAssignment $assignment): array
    {
        $warnings = [];

        if ($this->weeklyHours($assignment) <= 0) {
            $warnings[] = 'La UD no tiene horas semanales registradas.';
        }

        $shift = $assignment->shift;
        if (! $shift || ! $shift->start_time || ! $shift->end_time) {
            $warnings[] = 'El turno de la sección no tiene horario registrado.';
        }

        return $warnings;
    }

    /** "4", "2.5", "1.25". $minutesPerHour: duración de la hora (60, o la pedagógica del turno). */
    public function formatHours(int $minutes, int $minutesPerHour = 60): string
    {
        return rtrim(rtrim(number_format($minutes / max(1, $minutesPerHour), 2, '.', ''), '0'), '.');
    }

    private function weeklyHours(TeacherAssignment $assignment): float
    {
        return (float) ($assignment->didacticUnit->weekly_hours ?? 0);
    }

    private function minutesBetween(string $start, string $end): int
    {
        return (int) abs(Carbon::createFromFormat('H:i:s', TimeCast::normalize($start))
            ->diffInMinutes(Carbon::createFromFormat('H:i:s', TimeCast::normalize($end))));
    }

    /**
     * Cruces con bloques del mismo período y asignaciones activas (solapamiento estricto).
     * Trasladado desde ScheduleManager::findConflict() sin cambiar sus mensajes.
     */
    private function findConflict(
        TeacherAssignment $assignment,
        string $day,
        string $start,
        string $end,
        ?int $classroomId,
        ?int $ignoreScheduleId
    ): ?string {
        $periodId = $assignment->academic_period_id;

        $overlapping = fn () => Schedule::with(['teacherAssignment.didacticUnit', 'classroomResource'])
            ->when($ignoreScheduleId, fn ($q) => $q->whereKeyNot($ignoreScheduleId))
            ->where('day_of_week', $day)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->whereHas('teacherAssignment', fn ($q) => $q
                ->where('academic_period_id', $periodId)
                ->where('status', 'active'));

        $describe = fn (Schedule $s) => sprintf(
            '%s - Sección %s (%s a %s)',
            $s->teacherAssignment->didacticUnit->name ?? 'Curso',
            $s->teacherAssignment->section,
            $s->start_time->format('H:i'),
            $s->end_time->format('H:i')
        );

        // 1. Misma sección
        if ($s = $overlapping()->where('teacher_assignment_id', $assignment->id)->first()) {
            return 'Esta sección ya tiene un bloque en ese día y rango: '.$describe($s).'.';
        }

        // 2. Docente en otra sección (de cualquier programa)
        if ($s = $overlapping()
            ->where('teacher_assignment_id', '!=', $assignment->id)
            ->whereHas('teacherAssignment', fn ($q) => $q->where('teacher_id', $assignment->teacher_id))
            ->first()) {
            return 'El docente ya tiene clase en ese horario: '.$describe($s).'.';
        }

        // 3. Mismo grupo de estudiantes: programa + semestre + turno + sección
        $unit = $assignment->didacticUnit;
        $careerId = $unit->module->studyPlan->career_id ?? null;
        if ($careerId && $s = $overlapping()
            ->where('teacher_assignment_id', '!=', $assignment->id)
            ->whereHas('teacherAssignment', fn ($q) => $q
                ->where('shift_id', $assignment->shift_id)
                ->where('section', $assignment->section)
                ->whereHas('didacticUnit', fn ($u) => $u
                    ->where('semester', $unit->semester)
                    ->whereHas('module.studyPlan', fn ($p) => $p->where('career_id', $careerId))))
            ->first()) {
            return "Los estudiantes del semestre {$unit->semester}, sección {$assignment->section} ya tienen otra clase en ese horario: ".$describe($s).'.';
        }

        // 4. Aula
        if ($classroomId && $s = $overlapping()->where('classroom_resource_id', $classroomId)->first()) {
            return 'El aula '.($s->classroomResource->name ?? '').' ya está ocupada en ese horario: '.$describe($s).'.';
        }

        return null;
    }
}
