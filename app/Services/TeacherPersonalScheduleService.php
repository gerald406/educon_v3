<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Teacher;
use App\Models\TeacherAssignment;

/**
 * [NUEVO] Arma el horario personal de un docente en un período a partir de su carga
 * académica (teacher_assignments) y de los bloques de la gestión de horarios (schedules).
 *
 * Solo lectura: lo usan la página "Mi Horario" y el Excel oficial, para que ambos
 * muestren exactamente lo mismo. Solo considera secciones activas.
 */
class TeacherPersonalScheduleService
{
    /** Días de las plantillas oficiales (lunes a viernes). */
    public const DAY_LABELS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
    ];

    private const ROMAN = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];

    public function __construct(
        private PedagogicalHourService $pedagogicalHours,
        private ScheduleValidationService $validator,
    ) {}

    /**
     * @return array{
     *     teacher_name: string,
     *     period_code: string,
     *     title: string,
     *     rows: array<int, array<string, mixed>>,
     *     total_hours: int,
     *     grid: array<string, array<int, array<string, array<int, string>>>>,
     *     groups_with_blocks: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    public function build(Teacher $teacher, AcademicPeriod $period): array
    {
        $assignments = TeacherAssignment::with(['didacticUnit.module.studyPlan.career', 'shift', 'schedules'])
            ->where('teacher_id', $teacher->id)
            ->where('academic_period_id', $period->id)
            ->where('status', 'active')
            ->get()
            ->sortBy(fn (TeacherAssignment $a) => sprintf(
                '%s|%02d|%s|%s',
                mb_strtolower($this->programName($a)),
                $a->didacticUnit->semester ?? 0,
                mb_strtolower($a->didacticUnit->name ?? ''),
                $a->section
            ))
            ->values();

        $grid = $this->emptyGrid();
        $rows = [];
        $warnings = [];
        $groupsWithBlocks = [];

        foreach ($assignments as $a) {
            $unit = $a->didacticUnit;
            $unitName = trim($unit->name ?? 'Unidad didáctica');
            $program = $this->programName($a);
            $label = "{$unitName} - Sección {$a->section}";
            $weeklyHours = (int) ($unit->weekly_hours ?? 0);

            $rows[] = [
                'assignment_id' => $a->id,
                'program' => $program,
                'unit' => $unitName,
                'section' => $a->section,
                'semester_shift' => $this->semesterShift($a),
                'theory' => $unit->theory_hours,
                'practice' => $unit->practice_hours,
                'hours' => $weeklyHours,
            ];

            if ($unit->theory_hours === null || $unit->practice_hours === null) {
                $warnings[] = "{$label}: la unidad didáctica no tiene registradas las horas de teoría y práctica.";
            }

            foreach ($a->schedules as $s) {
                $start = $s->start_time->format('H:i');
                $end = $s->end_time->format('H:i');
                $when = sprintf('%s a %s', $start, $end);

                if (! isset(self::DAY_LABELS[$s->day_of_week])) {
                    $warnings[] = "{$label}: el bloque de {$when} está programado fuera de lunes a viernes y no se imprime.";

                    continue;
                }

                $slots = $this->pedagogicalHours->slotsTouching($start, $end);
                $day = self::DAY_LABELS[$s->day_of_week];

                if ($slots === []) {
                    $warnings[] = "{$label}: el bloque del {$day} de {$when} está fuera de las franjas oficiales y no se imprime.";

                    continue;
                }

                if (! $this->pedagogicalHours->fitsSlots($start, $end)) {
                    $warnings[] = "{$label}: el bloque del {$day} de {$when} no coincide con las franjas oficiales; se muestra en todas las franjas que ocupa.";
                }

                $text = $unitName.($s->session_type ? " ({$s->session_type})" : '').' - '.$program;

                foreach ($slots as $slot) {
                    $cell = &$grid[$slot['group']][$slot['index']][$s->day_of_week];
                    if (! in_array($text, $cell, true)) {
                        $cell[] = $text;
                    }
                    unset($cell);
                    $groupsWithBlocks[$slot['group']] = true;
                }
            }

            // Avance del horario respecto de las horas semanales de la UD
            $perHour = $this->validator->minutesPerHour($a);
            $scheduled = $this->validator->scheduledMinutes($a);
            $weekly = $this->validator->weeklyMinutes($a);
            if ($weekly > 0 && $scheduled !== $weekly) {
                $warnings[] = sprintf(
                    '%s: tiene %s h programadas de %s h semanales.',
                    $label,
                    $this->validator->formatHours($scheduled, $perHour),
                    $this->validator->formatHours($weekly, $perHour)
                );
            }
        }

        $user = $teacher->user;

        return [
            'teacher_name' => mb_strtoupper(trim(trim((string) $user?->lastname).', '.trim((string) $user?->name), ', ')),
            'period_code' => (string) $period->code,
            'title' => 'HORARIO PERSONAL DEL DOCENTE '.$period->code,
            'rows' => $rows,
            'total_hours' => (int) array_sum(array_column($rows, 'hours')),
            'grid' => $grid,
            'groups_with_blocks' => array_keys($groupsWithBlocks),
            'warnings' => $warnings,
        ];
    }

    /** Grilla vacía: jornada → franja → día → textos. */
    private function emptyGrid(): array
    {
        $grid = [];
        foreach (PedagogicalHourService::GROUPS as $group => $definition) {
            foreach (array_keys($definition['slots']) as $index) {
                $grid[$group][$index] = array_fill_keys(array_keys(self::DAY_LABELS), []);
            }
        }

        return $grid;
    }

    /** Nombre corto del programa; si no tiene, el nombre completo. */
    private function programName(TeacherAssignment $a): string
    {
        $career = $a->didacticUnit?->module?->studyPlan?->career;

        return trim((string) ($career?->short_name ?: $career?->name)) ?: 'Sin programa';
    }

    /** "V-DIURNO" */
    private function semesterShift(TeacherAssignment $a): string
    {
        $semester = (int) ($a->didacticUnit->semester ?? 0);

        return (self::ROMAN[$semester] ?? (string) $semester).'-'.mb_strtoupper(trim((string) ($a->shift->name ?? 'SIN TURNO')));
    }
}
