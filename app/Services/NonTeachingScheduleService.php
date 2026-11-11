<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\NonTeachingActivity;
use App\Models\NonTeachingBlock;
use App\Models\NonTeachingPlan;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Support\Facades\DB;

/**
 * [NUEVO] Reglas del horario no lectivo del docente (respuestas del usuario en
 * docs/archivos/DUDAS_IMPLEMENTACION_HORARIOS_DOCENTES.txt, apartados A–D):
 *
 *  - Jornada: 40 h (tiempo completo) o 20 h (tiempo parcial). Horas lectivas (pedagógicas)
 *    y no lectivas se suman como unidades; el total debe ser exactamente la jornada.
 *  - "Preparación de clases" es obligatoria y sus horas salen del Anexo 2 según la carga
 *    lectiva (UD asignadas); no se editan y se recalculan si la carga cambia.
 *  - Máximo 4 actividades (Preparación + 3), una subactividad por actividad (5–255
 *    caracteres), horas en múltiplos de 0.5 con mínimo 1.
 *  - Las horas se ubican de lunes a viernes entre 08:00 y 16:00 en bloques de 60 min que
 *    empiezan en punto; la media hora ocupa un bloque de 30 min. Un bloque no puede
 *    compartir minutos con una clase del docente ni con otro bloque no lectivo.
 *
 * Los métodos que modifican devuelven null si todo salió bien o el mensaje de error.
 * Todo se guarda en minutos para no arrastrar decimales.
 */
class NonTeachingScheduleService
{
    public const WORKDAY_HOURS = ['full_time' => 40, 'part_time' => 20];

    public const WORKDAY_LABELS = ['full_time' => 'Tiempo completo (40 h)', 'part_time' => 'Tiempo parcial (20 h)'];

    public const MAX_ACTIVITIES = 4;

    /** Franjas de la plantilla oficial "HORARIO NO LECTIVO": 8 horas de 60 min. */
    public const GRID_START = 8 * 60;

    public const GRID_END = 16 * 60;

    /** El Anexo 2 empieza en 4 horas lectivas. */
    public const MIN_LECTIVE_HOURS = 4;

    public const DAY_LABELS = TeacherPersonalScheduleService::DAY_LABELS;

    // ------------------------------------------------------------------
    // Cálculos
    // ------------------------------------------------------------------

    /**
     * Anexo 2: 4 h → 1; 5-6 → 1.5; 7-8 → 2; ... 19-20 → 5, y la misma progresión
     * para cargas mayores (21-22 → 5.5; ...). Devuelve minutos. Menos de 4 h → 0.
     */
    public function preparationMinutes(int $lectiveHours): int
    {
        if ($lectiveHours < self::MIN_LECTIVE_HOURS) {
            return 0;
        }

        return (int) ceil($lectiveHours / 2) * 30;
    }

    public function workdayHours(Teacher $teacher): int
    {
        return self::WORKDAY_HOURS[$teacher->workday_type] ?? self::WORKDAY_HOURS['full_time'];
    }

    /** Carga lectiva: suma de horas semanales de las UD asignadas (secciones activas). */
    public function lectiveHours(Teacher $teacher, AcademicPeriod $period): int
    {
        return (int) TeacherAssignment::with('didacticUnit')
            ->where('teacher_id', $teacher->id)
            ->where('academic_period_id', $period->id)
            ->where('status', 'active')
            ->get()
            ->sum(fn (TeacherAssignment $a) => (int) ($a->didacticUnit->weekly_hours ?? 0));
    }

    /**
     * Clases del docente por día, en minutos desde las 00:00.
     *
     * @return array<string, array<int, array{start: int, end: int, label: string}>>
     */
    public function lectiveIntervals(Teacher $teacher, AcademicPeriod $period): array
    {
        $intervals = array_fill_keys(array_keys(self::DAY_LABELS), []);

        $schedules = Schedule::with('teacherAssignment.didacticUnit')
            ->whereHas('teacherAssignment', fn ($q) => $q
                ->where('teacher_id', $teacher->id)
                ->where('academic_period_id', $period->id)
                ->where('status', 'active'))
            ->get();

        foreach ($schedules as $s) {
            if (! isset($intervals[$s->day_of_week])) {
                continue;
            }
            $intervals[$s->day_of_week][] = [
                'start' => $this->toMinutes($s->start_time->format('H:i')),
                'end' => $this->toMinutes($s->end_time->format('H:i')),
                'label' => $s->teacherAssignment->didacticUnit->name ?? 'Clase',
            ];
        }

        return $intervals;
    }

    // ------------------------------------------------------------------
    // Plan
    // ------------------------------------------------------------------

    /**
     * Crea el plan del período si falta y deja "Preparación de clases" con las horas
     * del Anexo 2 y la jornada vigente del docente. Idempotente.
     */
    public function ensurePlan(Teacher $teacher, AcademicPeriod $period): NonTeachingPlan
    {
        $plan = NonTeachingPlan::firstOrCreate([
            'teacher_id' => $teacher->id,
            'academic_period_id' => $period->id,
        ]);

        $workday = $this->workdayHours($teacher);
        if ((int) $plan->workday_hours !== $workday) {
            $plan->update(['workday_hours' => $workday]);
        }

        $minutes = $this->preparationMinutes($this->lectiveHours($teacher, $period));
        $preparation = NonTeachingActivity::firstOrCreate(
            ['teacher_non_teaching_plan_id' => $plan->id, 'activity' => NonTeachingActivity::PREPARATION],
            ['weekly_minutes' => $minutes]
        );
        if ($preparation->weekly_minutes !== $minutes) {
            $preparation->update(['weekly_minutes' => $minutes]);
        }

        return $plan;
    }

    /**
     * Estado completo del horario no lectivo para la pantalla y los documentos.
     */
    public function summary(Teacher $teacher, AcademicPeriod $period, ?NonTeachingPlan $plan = null): array
    {
        $plan ??= NonTeachingPlan::where('teacher_id', $teacher->id)->where('academic_period_id', $period->id)->first();

        $lectiveHours = $this->lectiveHours($teacher, $period);
        $workdayHours = $this->workdayHours($teacher);
        $lective = $this->lectiveIntervals($teacher, $period);

        $activities = $plan ? $plan->activities()->with('blocks')->get() : collect();
        $order = array_flip(array_keys(NonTeachingActivity::ACTIVITIES));
        // Preparación primero; el resto en el orden oficial
        $activities = $activities->sortBy(fn (NonTeachingActivity $a) => $a->isPreparation() ? -1 : ($order[$a->activity] ?? 99))->values();

        $issues = [];
        $rows = [];
        $blocks = [];
        $nonTeachingMinutes = 0;

        foreach ($activities as $index => $activity) {
            $placed = 0;
            foreach ($activity->blocks as $block) {
                $start = $this->toMinutes($block->start_time->format('H:i'));
                $end = $this->toMinutes($block->end_time->format('H:i'));
                $placed += $end - $start;
                $clash = $this->lectiveClash($lective, $block->day_of_week, $start, $end);
                $blocks[] = [
                    'id' => $block->id,
                    'activity_id' => $activity->id,
                    'index' => $index,
                    'label' => $activity->label(),
                    'day' => $block->day_of_week,
                    'start' => $start,
                    'end' => $end,
                    'conflict' => $clash,
                ];
                if ($clash) {
                    $issues[] = sprintf(
                        '%s: el bloque del %s de %s a %s se cruza con la clase de %s. Quítelo y ubíquelo en otra hora.',
                        $activity->label(),
                        mb_strtolower(self::DAY_LABELS[$block->day_of_week] ?? $block->day_of_week),
                        $this->toTime($start),
                        $this->toTime($end),
                        $clash
                    );
                }
            }

            $minutes = (int) $activity->weekly_minutes;
            $nonTeachingMinutes += $minutes;

            $rows[] = [
                'id' => $activity->id,
                'index' => $index,
                'key' => $activity->activity,
                'label' => $activity->label(),
                'is_preparation' => $activity->isPreparation(),
                'sub_activity' => (string) $activity->sub_activity,
                'minutes' => $minutes,
                'placed_minutes' => $placed,
            ];

            if ($minutes > 0 && trim((string) $activity->sub_activity) === '') {
                $issues[] = "{$activity->label()}: falta describir la subactividad.";
            }
            if ($placed < $minutes) {
                $issues[] = sprintf('%s: faltan ubicar %s h en la semana.', $activity->label(), $this->formatHours($minutes - $placed));
            } elseif ($placed > $minutes) {
                $issues[] = sprintf('%s: hay %s h ubicadas de más; quite bloques de la semana.', $activity->label(), $this->formatHours($placed - $minutes));
            }
        }

        $lectiveMinutes = $lectiveHours * 60; // se suman como unidades: 1 hora lectiva = 1
        $workdayMinutes = $workdayHours * 60;
        $totalMinutes = $lectiveMinutes + $nonTeachingMinutes;
        $difference = $workdayMinutes - $totalMinutes;

        if ($lectiveHours < self::MIN_LECTIVE_HOURS) {
            array_unshift($issues, sprintf(
                'Su carga lectiva es de %d h. El Anexo 2 asigna horas de preparación de clases desde %d horas lectivas: comuníquese con el área académica.',
                $lectiveHours,
                self::MIN_LECTIVE_HOURS
            ));
        }
        if ($difference > 0) {
            $issues[] = sprintf('Faltan %s h para completar su jornada de %d h semanales.', $this->formatHours($difference), $workdayHours);
        } elseif ($difference < 0) {
            $issues[] = sprintf(
                'Su carga supera en %s h la jornada de %d h semanales. Reduzca horas no lectivas o comuníquese con el área académica si la carga lectiva no es correcta.',
                $this->formatHours(-$difference),
                $workdayHours
            );
        }

        $checks = $this->checks($rows, $blocks, $lectiveHours, $workdayHours, $nonTeachingMinutes);

        return [
            'plan_id' => $plan?->id,
            'checks' => $checks,
            // true si el docente no puede completar el horario por sí mismo (lo corrige el área académica)
            'blocked_by_load' => ! $checks['load']['ok'],
            'workday_hours' => $workdayHours,
            'workday_label' => self::WORKDAY_LABELS[$teacher->workday_type] ?? self::WORKDAY_LABELS['full_time'],
            'lective_hours' => $lectiveHours,
            'preparation_minutes' => $this->preparationMinutes($lectiveHours),
            'non_teaching_minutes' => $nonTeachingMinutes,
            'total_minutes' => $totalMinutes,
            'workday_minutes' => $workdayMinutes,
            'difference_minutes' => $difference,
            'activities' => $rows,
            'available_activities' => array_diff_key(NonTeachingActivity::ACTIVITIES, array_flip(array_column($rows, 'key'))),
            'can_add_activity' => count($rows) < self::MAX_ACTIVITIES,
            'blocks' => $blocks,
            'lective' => $lective,
            'has_program' => $teacher->career_id !== null,
            'issues' => $issues,
            'is_complete' => $plan !== null && $issues === [],
        ];
    }

    /**
     * [NUEVO] Requisitos para descargar los documentos, en el orden en que el docente los
     * resuelve. Cada uno: label, ok, details (qué falta y cómo corregirlo) y who
     * ('teacher' si lo corrige el docente, 'office' si depende del área académica).
     * Es la misma información de `issues`, organizada para mostrarla como lista de pasos.
     */
    private function checks(array $rows, array $blocks, int $lectiveHours, int $workdayHours, int $nonTeachingMinutes): array
    {
        $workdayMinutes = $workdayHours * 60;
        $preparation = $this->preparationMinutes($lectiveHours);
        $difference = $workdayMinutes - ($lectiveHours * 60 + $nonTeachingMinutes);

        // 1. Carga lectiva: no depende del docente
        $load = [];
        if ($lectiveHours < self::MIN_LECTIVE_HOURS) {
            $load[] = sprintf(
                'Tiene %d h lectivas y el Anexo 2 asigna horas de preparación de clases desde %d. Solicite al área académica que revise su carga.',
                $lectiveHours,
                self::MIN_LECTIVE_HOURS
            );
        } elseif ($lectiveHours * 60 + $preparation > $workdayMinutes) {
            $load[] = sprintf(
                'Sus %d h lectivas más %s h de preparación de clases suman %s h y superan su jornada de %d h. '
                .'Usted no puede corregirlo desde aquí: solicite al área académica que revise su carga lectiva o su condición laboral.',
                $lectiveHours,
                $this->formatHours($preparation),
                $this->formatHours($lectiveHours * 60 + $preparation),
                $workdayHours
            );
        }

        // 2. Subactividades
        $subActivities = [];
        foreach ($rows as $row) {
            if ($row['minutes'] > 0 && trim($row['sub_activity']) === '') {
                $subActivities[] = "{$row['label']}: pulse «".($row['is_preparation'] ? 'Describir' : 'Editar').'» y escriba qué realizará.';
            }
        }

        // 3. Jornada exacta
        $total = [];
        if ($load !== [] && $difference < 0) {
            $total[] = 'Se resolverá cuando el área académica corrija su carga lectiva.';
        } elseif ($difference > 0) {
            $total[] = sprintf('Faltan %s h: agregue una actividad o aumente las horas de una existente.', $this->formatHours($difference));
        } elseif ($difference < 0) {
            $total[] = sprintf('Sobran %s h: reduzca las horas de una actividad.', $this->formatHours(-$difference));
        }

        // 4. Horas ubicadas en la semana
        $placed = [];
        $placedMinutes = 0;
        foreach ($rows as $row) {
            $placedMinutes += min($row['placed_minutes'], $row['minutes']);
            if ($row['placed_minutes'] < $row['minutes']) {
                $placed[] = sprintf('%s: faltan ubicar %s h. Pulse «Ubicar en la semana» y elija casillas libres.', $row['label'], $this->formatHours($row['minutes'] - $row['placed_minutes']));
            } elseif ($row['placed_minutes'] > $row['minutes']) {
                $placed[] = sprintf('%s: hay %s h ubicadas de más. Elimine un bloque de la semana con el icono rojo.', $row['label'], $this->formatHours($row['placed_minutes'] - $row['minutes']));
            }
        }

        // 5. Cruces con clases
        $conflicts = [];
        foreach ($blocks as $block) {
            if ($block['conflict']) {
                $conflicts[] = sprintf(
                    '%s, %s de %s a %s: ahora tiene clase de %s. Elimínelo con el icono rojo y ubíquelo en otra hora.',
                    $block['label'],
                    mb_strtolower(self::DAY_LABELS[$block['day']] ?? $block['day']),
                    $this->toTime($block['start']),
                    $this->toTime($block['end']),
                    $block['conflict']
                );
            }
        }

        $check = fn (string $label, array $details, string $who = 'teacher') => [
            'label' => $label, 'ok' => $details === [], 'details' => $details, 'who' => $who,
        ];

        return [
            'load' => $check('Carga lectiva compatible con su jornada', $load, 'office'),
            'sub_activities' => $check('Subactividades descritas', $subActivities),
            'total' => $check(sprintf(
                'Jornada completa: %s de %d horas',
                $this->formatHours($lectiveHours * 60 + $nonTeachingMinutes),
                $workdayHours
            ), $total, $load !== [] && $difference < 0 ? 'office' : 'teacher'),
            'placed' => $check(sprintf(
                'Horas ubicadas en la semana: %s de %s',
                $this->formatHours($placedMinutes),
                $this->formatHours($nonTeachingMinutes)
            ), $placed),
            'conflicts' => $check('Sin cruces con sus clases', $conflicts),
        ];
    }

    // ------------------------------------------------------------------
    // Actividades
    // ------------------------------------------------------------------

    /**
     * Crea o actualiza una actividad del plan. En "Preparación de clases" solo se guarda
     * la subactividad: sus horas son las del Anexo 2.
     */
    public function saveActivity(NonTeachingPlan $plan, ?int $activityId, string $key, string $subActivity, mixed $hours): ?string
    {
        $teacher = $plan->teacher;
        $period = $plan->academicPeriod;
        $subActivity = trim($subActivity);

        if (mb_strlen($subActivity) < 5 || mb_strlen($subActivity) > 255) {
            return 'Describa la subactividad con un texto de 5 a 255 caracteres.';
        }

        $existing = $activityId ? $plan->activities()->find($activityId) : null;
        if ($activityId && ! $existing) {
            return 'La actividad ya no existe. Actualice la página.';
        }

        if ($existing?->isPreparation()) {
            $existing->update(['sub_activity' => $subActivity]);

            return null;
        }

        if (! isset(NonTeachingActivity::ACTIVITIES[$key]) || $key === NonTeachingActivity::PREPARATION) {
            return 'Seleccione una actividad de la lista.';
        }

        $duplicate = $plan->activities()->where('activity', $key)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists();
        if ($duplicate) {
            return 'Esa actividad ya está registrada: cada actividad lleva una sola subactividad.';
        }

        if (! $existing && $plan->activities()->count() >= self::MAX_ACTIVITIES) {
            return 'Puede registrar como máximo '.self::MAX_ACTIVITIES.' actividades, incluida Preparación de clases.';
        }

        if (! is_numeric($hours) || (float) $hours < 1 || fmod((float) $hours * 2, 1.0) !== 0.0) {
            return 'Las horas semanales deben ser 1 o más, en horas completas o medias horas (ej. 2 o 2.5).';
        }
        $minutes = (int) round((float) $hours * 60);

        $others = (int) $plan->activities()->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->sum('weekly_minutes');
        $workdayMinutes = $this->workdayHours($teacher) * 60;
        $available = $workdayMinutes - $this->lectiveHours($teacher, $period) * 60 - $others;
        if ($minutes > $available) {
            return sprintf(
                'Con %s h superaría su jornada de %d h semanales. Puede asignar hasta %s h a esta actividad.',
                $this->formatHours($minutes),
                $this->workdayHours($teacher),
                $this->formatHours(max(0, $available))
            );
        }

        if ($existing) {
            $placed = $this->placedMinutes($existing);
            if ($minutes < $placed) {
                return sprintf('Ya tiene %s h ubicadas en la semana: quite bloques antes de reducir las horas.', $this->formatHours($placed));
            }
            // Si la media hora desaparece, su bloque de 30 min ya no corresponde
            if ($minutes % 60 === 0 && $existing->blocks->contains(fn (NonTeachingBlock $b) => $b->minutes() === 30)) {
                return 'Quite primero el bloque de media hora de la semana.';
            }
        }

        $data = ['activity' => $key, 'sub_activity' => $subActivity, 'weekly_minutes' => $minutes];
        $existing ? $existing->update($data) : $plan->activities()->create($data);

        return null;
    }

    /** Quita una actividad y sus bloques. "Preparación de clases" no se puede quitar. */
    public function removeActivity(NonTeachingPlan $plan, int $activityId): ?string
    {
        $activity = $plan->activities()->find($activityId);
        if (! $activity) {
            return null;
        }
        if ($activity->isPreparation()) {
            return 'Preparación de clases es obligatoria y no se puede quitar.';
        }

        DB::transaction(function () use ($activity) {
            $activity->blocks()->delete();
            $activity->delete();
        });

        return null;
    }

    // ------------------------------------------------------------------
    // Bloques en la semana
    // ------------------------------------------------------------------

    /**
     * Ubica un bloque de la actividad en la media hora indicada ($time = 'HH:00' u 'HH:30').
     * Primero intenta la hora completa (HH:00–HH+1:00); si la actividad solo tiene pendiente
     * su media hora, ubica 30 min en la mitad indicada.
     */
    public function placeBlock(NonTeachingPlan $plan, int $activityId, string $day, string $time): ?string
    {
        $activity = $plan->activities()->with('blocks')->find($activityId);
        if (! $activity) {
            return 'Seleccione primero una actividad de la lista.';
        }
        if (! isset(self::DAY_LABELS[$day]) || ! preg_match('/^\d{2}:(00|30)$/', $time)) {
            return 'Casilla no válida.';
        }

        $half = $this->toMinutes($time);
        if ($half < self::GRID_START || $half + 30 > self::GRID_END) {
            return 'Las horas no lectivas se ubican entre las 08:00 y las 16:00.';
        }

        $pending = (int) $activity->weekly_minutes - $this->placedMinutes($activity);
        if ($pending <= 0) {
            return sprintf('%s ya tiene todas sus horas ubicadas (%s h).', $activity->label(), $this->formatHours((int) $activity->weekly_minutes));
        }

        $lective = $this->lectiveIntervals($plan->teacher, $plan->academicPeriod);
        $taken = $plan->blocks()->get()->map(fn (NonTeachingBlock $b) => [
            'day' => $b->day_of_week,
            'start' => $this->toMinutes($b->start_time->format('H:i')),
            'end' => $this->toMinutes($b->end_time->format('H:i')),
        ]);
        $free = fn (int $start, int $end) => ! $this->lectiveClash($lective, $day, $start, $end)
            && ! $taken->contains(fn ($b) => $b['day'] === $day && $start < $b['end'] && $end > $b['start']);

        $hour = intdiv($half, 60) * 60;

        if ($pending >= 60 && $free($hour, $hour + 60)) {
            [$start, $end] = [$hour, $hour + 60];
        } elseif ($pending % 60 === 30 && $free($half, $half + 30)) {
            // Media hora: solo cuando ya no cabe (o no queda) una hora completa en esta casilla
            [$start, $end] = [$half, $half + 30];
        } elseif ($clash = $this->lectiveClash($lective, $day, $half, $half + 30)) {
            return "En ese horario tiene clase de {$clash}. Elija una hora libre.";
        } elseif (! $free($half, $half + 30)) {
            return 'Esa casilla ya está ocupada por otra actividad no lectiva.';
        } else {
            // La media hora está libre, pero lo pendiente son horas completas y la hora no lo está
            return 'Esa hora no está completamente libre. Las horas no lectivas ocupan bloques de 60 minutos: elija una hora libre.';
        }

        NonTeachingBlock::create([
            'teacher_non_teaching_plan_id' => $plan->id,
            'teacher_non_teaching_activity_id' => $activity->id,
            'day_of_week' => $day,
            'start_time' => $this->toTime($start),
            'end_time' => $this->toTime($end),
        ]);

        return null;
    }

    public function removeBlock(NonTeachingPlan $plan, int $blockId): void
    {
        $plan->blocks()->whereKey($blockId)->delete();
    }

    // ------------------------------------------------------------------
    // Copia desde el período anterior
    // ------------------------------------------------------------------

    /** Último plan del docente en otro período que tenga actividades además de Preparación. */
    public function previousPlan(NonTeachingPlan $plan): ?NonTeachingPlan
    {
        return NonTeachingPlan::where('teacher_id', $plan->teacher_id)
            ->where('academic_period_id', '!=', $plan->academic_period_id)
            ->whereHas('activities', fn ($q) => $q->where('activity', '!=', NonTeachingActivity::PREPARATION))
            ->orderByDesc('academic_period_id')
            ->first();
    }

    /** true si el plan del período aún no tiene actividades propias y existe uno anterior que copiar. */
    public function canCopyFromPrevious(Teacher $teacher, AcademicPeriod $period): bool
    {
        $plan = NonTeachingPlan::where('teacher_id', $teacher->id)->where('academic_period_id', $period->id)->first();

        return $plan !== null
            && ! $plan->activities()->where('activity', '!=', NonTeachingActivity::PREPARATION)->exists()
            && $this->previousPlan($plan) !== null;
    }

    /**
     * Copia al plan actual las actividades, subactividades y bloques del período anterior.
     * Solo si aún no registró actividades propias. Las horas de Preparación son las del
     * período actual; lo que no cabe en la jornada o choca con clases no se copia.
     * Devuelve [mensaje, éxito].
     *
     * @return array{0: string, 1: bool}
     */
    public function copyFromPrevious(NonTeachingPlan $plan): array
    {
        if ($plan->activities()->where('activity', '!=', NonTeachingActivity::PREPARATION)->exists()) {
            return ['Ya registró actividades en este período: la copia solo está disponible con el horario vacío.', false];
        }

        $source = $this->previousPlan($plan);
        if (! $source) {
            return ['No tiene un horario no lectivo registrado en un período anterior.', false];
        }

        $skipped = [];
        $pendingBlocks = 0;

        DB::transaction(function () use ($plan, $source, &$skipped, &$pendingBlocks) {
            foreach ($source->activities()->with('blocks')->get() as $old) {
                if ($old->isPreparation()) {
                    $new = $plan->activities()->where('activity', NonTeachingActivity::PREPARATION)->first();
                    if ($new && trim((string) $new->sub_activity) === '' && trim((string) $old->sub_activity) !== '') {
                        $new->update(['sub_activity' => $old->sub_activity]);
                    }
                } else {
                    $error = $this->saveActivity($plan, null, $old->activity, (string) $old->sub_activity, $old->weekly_minutes / 60);
                    if ($error) {
                        $skipped[] = $old->label();

                        continue;
                    }
                    $new = $plan->activities()->where('activity', $old->activity)->first();
                }

                if (! $new) {
                    continue;
                }

                // Primero las horas completas y después la media hora
                foreach ($old->blocks->sortByDesc(fn (NonTeachingBlock $b) => $b->minutes()) as $block) {
                    if ($this->placeBlock($plan, $new->id, $block->day_of_week, $block->start_time->format('H:i'))) {
                        $pendingBlocks++;
                    }
                }
            }
        });

        $message = 'Se copió su horario no lectivo del período anterior.';
        if ($skipped) {
            $message .= ' No se copiaron por superar su jornada actual: '.implode(', ', $skipped).'.';
        }
        if ($pendingBlocks) {
            $message .= " {$pendingBlocks} bloque(s) no se ubicaron porque ahora chocan con sus clases o ya no corresponden: ubíquelos de nuevo.";
        }

        return [$message.' Revise las horas antes de descargar sus documentos.', true];
    }

    // ------------------------------------------------------------------
    // Documentos
    // ------------------------------------------------------------------

    /**
     * Datos para el Excel "Horario no lectivo" y el PDF "Programación de actividades":
     * carga lectiva (la misma del horario personal) + lo que el docente configuró.
     */
    public function documentData(Teacher $teacher, AcademicPeriod $period): array
    {
        $personal = app(TeacherPersonalScheduleService::class)->build($teacher, $period);
        $summary = $this->summary($teacher, $period);
        $letters = array_combine(array_keys(NonTeachingActivity::ACTIVITIES), range('a', 'h'));

        return [
            'teacher_name' => $personal['teacher_name'],
            'period_code' => (string) $period->code,
            'program' => (string) ($teacher->career?->name ?? ''),
            'contract_type' => $teacher->contract_type,
            'workday_hours' => $summary['workday_hours'],
            'lective_rows' => $personal['rows'],
            'lective_hours' => $summary['lective_hours'],
            'activities' => array_map(fn (array $a) => $a + [
                'letter' => $letters[$a['key']] ?? '',
                'hours' => $a['minutes'] / 60,
            ], array_values(array_filter($summary['activities'], fn (array $a) => $a['minutes'] > 0))),
            'non_teaching_hours' => $summary['non_teaching_minutes'] / 60,
            'total_hours' => $summary['total_minutes'] / 60,
            'blocks' => $summary['blocks'],
            'is_complete' => $summary['is_complete'],
            'issues' => $summary['issues'],
        ];
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** "2", "1.5" */
    public function formatHours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 2, '.', ''), '0'), '.');
    }

    public function toMinutes(string $time): int
    {
        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }

    public function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function placedMinutes(NonTeachingActivity $activity): int
    {
        return (int) $activity->blocks->sum(fn (NonTeachingBlock $b) => $b->minutes());
    }

    /** Nombre de la clase que comparte minutos con [start, end) ese día, o null. */
    private function lectiveClash(array $lective, string $day, int $start, int $end): ?string
    {
        foreach ($lective[$day] ?? [] as $class) {
            if ($start < $class['end'] && $end > $class['start']) {
                return $class['label'];
            }
        }

        return null;
    }
}
