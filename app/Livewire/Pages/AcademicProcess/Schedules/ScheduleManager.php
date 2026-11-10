<?php

namespace App\Livewire\Pages\AcademicProcess\Schedules;

use App\Casts\TimeCast;
use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\ClassroomResource;
use App\Models\DidacticUnit;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Services\ScheduleValidationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * [MODIFICADO] Gestión de horarios por docente:
 * programa → docente (con carga académica en el programa) → grilla completa del docente
 * → UD asignadas en el programa → bloques. Las reglas (turno, horas semanales y cruces)
 * viven en ScheduleValidationService; todo id del formulario se vuelve a resolver en el servidor.
 */
#[Layout('layouts.app')]
class ScheduleManager extends Component
{
    use AuthorizesRequests;

    // ==========================================
    // PROPIEDADES - GESTIÓN POR DOCENTE
    // ==========================================

    public ?AcademicPeriod $activePeriod = null;

    // Selección: programa → docente → sección (asignación de la carga académica)
    public $selectedCareerId = '';
    public $selectedTeacherId = '';
    public $selectedAssignmentId = '';

    // Formulario de bloque
    public $classroom_resource_id = '';
    public $day_of_week = 'monday';
    public $start_time = '';
    public $end_time = '';
    public $editingScheduleId = null;

    // Colecciones
    public Collection $careers;
    public Collection $classrooms;
    public Collection $shifts;
    public Collection $teachers; // Modal de exportación

    // ==========================================
    // PROPIEDADES - EXPORTACIÓN (SIMPLIFICADO)
    // ==========================================

    public $showExportModal = false;
    public $exportType = 'teacher'; // Solo: 'teacher' o 'career'
    public $exportFormat = 'pdf';   // 'pdf' o 'excel'

    // Filtros específicos por tipo
    public $exportTeacherId = '';
    public $exportCareerId = '';
    public $exportSemester = '';
    public $exportShiftId = '';

    // ==========================================
    // PROPIEDADES - VISTA CONSOLIDADA
    // ==========================================
    public $viewMode = 'teacher'; // [MODIFICADO] 'teacher' (por docente) o 'consolidated'
    public $filterCycleId = ''; // Semestre o Ciclo seleccionado
    public $filterShiftId = ''; // Turno seleccionado

    // ==========================================
    // INICIALIZACIÓN
    // ==========================================

    public function mount()
    {
        $this->activePeriod = AcademicPeriod::where('status', 'active')->first();

        $this->careers = Career::where('status', 'active')
            ->orderBy('name')
            ->get();

        $this->classrooms = ClassroomResource::where('status', 'available')
            ->orderBy('name')
            ->get();

        $this->shifts = Shift::where('status', 'active')
            ->orderBy('name')
            ->get();

        $this->teachers = Teacher::with('user')
            ->where('status', 'active')
            ->get()
            ->sortBy('user.lastname');
    }

    private function validator(): ScheduleValidationService
    {
        return app(ScheduleValidationService::class);
    }

    // ==========================================
    // [NUEVO] SELECCIÓN: PROGRAMA → DOCENTE → SECCIÓN
    // ==========================================

    public function updatedSelectedCareerId()
    {
        $this->selectedTeacherId = '';
        $this->clearAssignment();
    }

    public function updatedSelectedTeacherId($value)
    {
        $this->clearAssignment();

        // Solo docentes con carga en el programa seleccionado
        if ($value && !$this->programTeachers->contains('id', (int) $value)) {
            $this->selectedTeacherId = '';
        }
    }

    private function clearAssignment(): void
    {
        $this->selectedAssignmentId = '';
        $this->resetBlockForm();
        unset($this->teacherGrid, $this->teacherAssignments, $this->selectedAssignment);
    }

    /** Asignaciones activas del período activo cuyas UD pertenecen al programa seleccionado. */
    private function programAssignmentsQuery()
    {
        return TeacherAssignment::query()
            ->where('academic_period_id', $this->activePeriod?->id)
            ->where('status', 'active')
            ->whereHas('didacticUnit.module.studyPlan', fn ($q) => $q->where('career_id', $this->selectedCareerId));
    }

    /**
     * Vuelve a resolver la sección en el servidor: período activo, activa,
     * del docente seleccionado y con UD del programa seleccionado.
     */
    private function resolveAssignment($id): ?TeacherAssignment
    {
        if (!$id || !$this->activePeriod || !$this->selectedCareerId || !$this->selectedTeacherId) {
            return null;
        }

        return $this->programAssignmentsQuery()
            ->where('teacher_id', $this->selectedTeacherId)
            ->with(['didacticUnit.module.studyPlan', 'shift'])
            ->find($id);
    }

    /** Docentes con carga académica en el programa (con el número de secciones). */
    #[Computed]
    public function programTeachers(): Collection
    {
        if (!$this->activePeriod || !$this->selectedCareerId) {
            return collect();
        }

        $counts = $this->programAssignmentsQuery()
            ->selectRaw('teacher_id, count(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        return Teacher::with('user')
            ->whereIn('id', $counts->keys())
            ->get()
            ->each(fn (Teacher $t) => $t->assignments_count = (int) $counts[$t->id])
            ->sortBy(fn (Teacher $t) => mb_strtolower(($t->user->lastname ?? '') . ' ' . ($t->user->name ?? '')))
            ->values();
    }

    /** Secciones del docente en el programa, con minutos programados y restantes. */
    #[Computed]
    public function teacherAssignments(): Collection
    {
        if (!$this->activePeriod || !$this->selectedCareerId || !$this->selectedTeacherId) {
            return collect();
        }

        $validator = $this->validator();

        return $this->programAssignmentsQuery()
            ->where('teacher_id', $this->selectedTeacherId)
            ->with(['didacticUnit', 'shift'])
            ->get()
            ->sortBy(fn ($a) => sprintf('%02d-%s-%s', $a->didacticUnit->semester ?? 0, $a->didacticUnit->name ?? '', $a->section))
            ->each(function (TeacherAssignment $a) use ($validator) {
                $a->scheduled_minutes = $validator->scheduledMinutes($a);
                $a->remaining_minutes = $validator->remainingMinutes($a);
                $a->warnings = $validator->warnings($a);
            })
            ->values();
    }

    #[Computed]
    public function selectedAssignment(): ?TeacherAssignment
    {
        return $this->resolveAssignment($this->selectedAssignmentId);
    }

    /**
     * Grilla con TODOS los bloques del docente en el período activo.
     * Los de otros programas son de solo lectura. El rango cubre sus turnos y sus bloques.
     */
    #[Computed]
    public function teacherGrid(): array
    {
        $days = ScheduleValidationService::DAYS;
        $grid = ['days' => $days, 'from' => null, 'to' => null, 'blocks' => array_fill_keys($days, [])];

        if ($this->activePeriod && $this->selectedTeacherId) {
            $validator = $this->validator();
            $assignments = TeacherAssignment::with(['didacticUnit.module.studyPlan.career', 'shift', 'schedules.classroomResource'])
                ->where('academic_period_id', $this->activePeriod->id)
                ->where('teacher_id', $this->selectedTeacherId)
                ->where('status', 'active')
                ->get();

            $widen = function (?string $from, ?string $to) use (&$grid) {
                if ($from && ($grid['from'] === null || $from < $grid['from'])) {
                    $grid['from'] = $from;
                }
                if ($to && ($grid['to'] === null || $to > $grid['to'])) {
                    $grid['to'] = $to;
                }
            };

            foreach ($assignments as $a) {
                $widen($a->shift?->start_time?->format('H:i'), $a->shift?->end_time?->format('H:i'));
                $career = $a->didacticUnit->module->studyPlan->career ?? null;

                foreach ($a->schedules as $s) {
                    $start = $s->start_time->format('H:i');
                    $end = $s->end_time->format('H:i');
                    $widen($start, $end);

                    if (!isset($grid['blocks'][$s->day_of_week])) {
                        continue;
                    }

                    $grid['blocks'][$s->day_of_week][] = [
                        'id' => $s->id,
                        'assignment_id' => $a->id,
                        'unit' => $a->didacticUnit->name ?? 'Curso',
                        'section' => $a->section,
                        'classroom' => $s->classroomResource->name ?? null,
                        'start' => $start,
                        'end' => $end,
                        'editable' => $career && (int) $career->id === (int) $this->selectedCareerId,
                        'program' => $career->name ?? '',
                        'out_of_shift' => $validator->isWithinShift($a, $start, $end) === false,
                    ];
                }
            }

            foreach ($grid['blocks'] as $day => $blocks) {
                usort($blocks, fn ($x, $y) => strcmp($x['start'], $y['start']));
                $grid['blocks'][$day] = $blocks;
            }
        }

        $grid['from'] ??= '07:00';
        $grid['to'] ??= '23:00';

        return $grid;
    }

    public function selectAssignment($id)
    {
        $assignment = $this->resolveAssignment($id);

        $this->resetBlockForm();
        $this->selectedAssignmentId = $assignment?->id ?? '';
        unset($this->selectedAssignment);
    }

    // ==========================================
    // FORMULARIO DE BLOQUES
    // ==========================================

    /** Carga un bloque del programa seleccionado en el formulario (y selecciona su UD). */
    public function editSchedule($id)
    {
        $schedule = Schedule::find($id);
        $assignment = $schedule ? $this->resolveAssignment($schedule->teacher_assignment_id) : null;
        if (!$assignment) {
            return;
        }

        $this->selectedAssignmentId = $assignment->id;
        unset($this->selectedAssignment);
        $this->editingScheduleId = $schedule->id;
        $this->day_of_week = $schedule->day_of_week;
        $this->start_time = $schedule->start_time->format('H:i');
        $this->end_time = $schedule->end_time->format('H:i');
        $this->classroom_resource_id = $schedule->classroom_resource_id ?? '';
        $this->resetValidation();

        // [NUEVO] Si el aula del bloque ya no está disponible, no figura en el select:
        // se quita del formulario y se avisa (antes el guardado fallaba sin forma clara de corregirlo).
        if ($schedule->classroom_resource_id) {
            $room = ClassroomResource::find($schedule->classroom_resource_id);
            if (!$room || $room->status !== 'available') {
                $this->classroom_resource_id = '';
                $this->dispatch('swal', [
                    'icon' => 'warning',
                    'title' => 'Aula no disponible',
                    'text' => 'El aula ' . ($room->name ?? '') . ' ya no está disponible: seleccione otra o deje el bloque sin aula antes de guardar.',
                ]);
            }
        }
    }

    public function cancelEdit()
    {
        $this->resetBlockForm();
    }

    /** Rellena inicio y fin con el horario del turno de la sección. */
    public function useShiftHours()
    {
        $shift = $this->selectedAssignment?->shift;
        if ($shift?->start_time && $shift?->end_time) {
            $this->start_time = $shift->start_time->format('H:i');
            $this->end_time = $shift->end_time->format('H:i');
        }
    }

    private function resetBlockForm(): void
    {
        $this->editingScheduleId = null;
        $this->day_of_week = 'monday';
        $this->start_time = '';
        $this->end_time = '';
        $this->classroom_resource_id = '';
        $this->resetValidation();
    }

    /**
     * Crea o actualiza un bloque de la sección seleccionada.
     * Valida turno, horas semanales y cruces con ScheduleValidationService.
     */
    public function saveSchedule()
    {
        $this->authorize('gestionar-horarios');

        $assignment = $this->resolveAssignment($this->selectedAssignmentId);
        if (!$assignment) {
            $this->selectedAssignmentId = '';
            $this->dispatch('swal', [
                'icon'  => 'warning',
                'title' => 'Seleccione una unidad didáctica',
                'text'  => 'Seleccione el programa, el docente y una de sus unidades didácticas antes de registrar el bloque.',
            ]);
            return;
        }

        $this->validate([
            'day_of_week'           => 'required|in:' . implode(',', ScheduleValidationService::DAYS),
            'start_time'            => 'required|date_format:H:i',
            'end_time'              => 'required|date_format:H:i|after:start_time',
            'classroom_resource_id' => 'nullable|exists:classroom_resources,id',
        ]);

        $editing = null;
        if ($this->editingScheduleId) {
            $editing = Schedule::where('teacher_assignment_id', $assignment->id)->find($this->editingScheduleId);
            if (!$editing) {
                $this->resetBlockForm();
                return;
            }
        }

        $classroomId = $this->classroom_resource_id ? (int) $this->classroom_resource_id : null;

        if ($message = $this->validator()->validate($assignment, $this->day_of_week, $this->start_time, $this->end_time, $classroomId, $editing?->id)) {
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'No se puede guardar el bloque', 'text' => $message]);
            return;
        }

        $data = [
            'teacher_assignment_id' => $assignment->id,
            'day_of_week'           => $this->day_of_week,
            'start_time'            => TimeCast::normalize($this->start_time),
            'end_time'              => TimeCast::normalize($this->end_time),
            'classroom_resource_id' => $classroomId,
        ];

        $editing ? $editing->update($data) : Schedule::create($data);

        $this->dispatch('swal', [
            'icon'  => 'success',
            'title' => $editing ? 'Actualizado' : 'Agregado',
            'text'  => $editing ? 'Bloque horario actualizado.' : 'Bloque horario registrado correctamente.',
        ]);

        $this->resetBlockForm();
        unset($this->teacherGrid, $this->teacherAssignments);
    }

    /** Pide confirmación (SweetAlert) antes de eliminar un bloque. */
    public function confirmDeleteSchedule($id)
    {
        $schedule = Schedule::with('teacherAssignment.didacticUnit')->find($id);
        if (!$schedule || !$this->resolveAssignment($schedule->teacher_assignment_id)) {
            return;
        }

        $this->dispatch('swal:confirm', [
            'title' => '¿Eliminar este bloque?',
            'text' => sprintf(
                '%s - Sección %s (%s a %s).',
                $schedule->teacherAssignment->didacticUnit->name ?? 'Curso',
                $schedule->teacherAssignment->section,
                $schedule->start_time->format('H:i'),
                $schedule->end_time->format('H:i')
            ),
            'icon' => 'warning',
            'confirmButtonText' => 'Sí, eliminar',
            'id' => $schedule->id,
            'onConfirmed' => 'deleteSchedule',
        ]);
    }

    #[On('deleteSchedule')]
    public function deleteSchedule($id)
    {
        $this->authorize('gestionar-horarios');

        // Solo bloques de secciones del docente y programa seleccionados
        $schedule = Schedule::find($id);
        if (!$schedule || !$this->resolveAssignment($schedule->teacher_assignment_id)) {
            return;
        }

        $schedule->delete();

        if ((int) $this->editingScheduleId === (int) $id) {
            $this->resetBlockForm();
        }

        unset($this->teacherGrid, $this->teacherAssignments);

        $this->dispatch('swal', ['icon' => 'success', 'title' => 'Eliminado', 'text' => 'Bloque eliminado.']);
    }

    // ==========================================
    // EXPORTACIÓN - LÓGICA SIMPLIFICADA
    // ==========================================

    /**
     * Resetear modal al abrirlo
     */
    public function updatedShowExportModal($value)
    {
        if ($value) {
            $this->exportType = 'teacher';
            $this->exportFormat = 'pdf';
            $this->exportTeacherId = '';
            $this->exportCareerId = '';
            $this->exportSemester = '';
            $this->exportShiftId = '';
        }
    }

    /**
     * Al cambiar tipo de reporte, limpiar campos específicos
     */
    public function updatedExportType()
    {
        $this->exportTeacherId = '';
        $this->exportCareerId = '';
        $this->exportSemester = '';
        $this->exportShiftId = '';
    }

    /**
     * Propiedad computada: Lista de semestres disponibles
     */
    #[Computed]
    public function availableSemesters()
    {
        if (!$this->exportCareerId || !$this->activePeriod) {
            return collect();
        }

        return TeacherAssignment::where('academic_period_id', $this->activePeriod->id)
            ->where('status', 'active')
            ->whereHas(
                'didacticUnit.module.studyPlan',
                fn($q) =>
                $q->where('career_id', $this->exportCareerId)
            )
            ->with('didacticUnit')
            ->get()
            ->pluck('didacticUnit.semester')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Validación: ¿Se puede exportar?
     */
    #[Computed]
    public function canExport()
    {
        if (!$this->activePeriod) {
            return false;
        }

        if ($this->exportType === 'teacher') {
            return !empty($this->exportTeacherId);
        }

        if ($this->exportType === 'career') {
            return !empty($this->exportCareerId) &&
                !empty($this->exportSemester) &&
                !empty($this->exportShiftId);
        }

        return false;
    }

    // ==========================================
    // NUEVA VISTA CONSOLIDADA SEMESTRAL
    // ==========================================

    /**
     * Alternar entre vista por Unidad o Consolidada
     */
    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
        $this->resetValidation();
    }

    /**
     * Obtener el horario de todo el semestre/turno agrupado por días
     */
    #[Computed]
    public function consolidatedSchedule()
    {
        if (!$this->selectedCareerId || !$this->filterCycleId || !$this->filterShiftId || !$this->activePeriod) {
            return collect();
        }

        // Buscar todos los horarios que coincidan con Programa, Semestre y Turno
        $schedules = Schedule::with([
            'teacherAssignment.teacher.user',
            'teacherAssignment.didacticUnit',
            'classroomResource'
        ])
            ->whereHas('teacherAssignment', function ($q) {
                $q->where('academic_period_id', $this->activePeriod->id)
                    ->where('status', 'active') // [CORREGIDO] solo secciones activas (igual que la validación de cruces)
                    ->where('shift_id', $this->filterShiftId) // Filtro por Turno
                    ->whereHas('didacticUnit', function ($q2) {
                        $q2->where('semester', $this->filterCycleId) // Filtro por Semestre
                            ->whereHas('module.studyPlan', function ($q3) {
                                $q3->where('career_id', $this->selectedCareerId); // Filtro por Programa
                            });
                    });
            })
            ->orderBy('start_time')
            ->get();

        // Retornar agrupado por día de la semana
        return $schedules->groupBy('day_of_week');
    }

    /**
     * Indicadores rápidos para la vista consolidada
     */
    #[Computed]
    public function progressStats()
    {
        if (!$this->selectedCareerId || !$this->filterCycleId || !$this->activePeriod) {
            // [CORREGIDO] 'percentage' faltaba: la vista consolidada fallaba sin período activo
            return ['total_courses' => 0, 'scheduled_courses' => 0, 'percentage' => 0];
        }

        // Total de cursos del semestre
        $totalCourses = DidacticUnit::where('semester', $this->filterCycleId)
            ->where('status', 'active')
            ->whereHas('module.studyPlan', function ($q) {
                $q->where('career_id', $this->selectedCareerId);
            })->count();

        // Cursos (UD distintas) con al menos una sección activa con horario en este turno.
        // [CORREGIDO] Antes contaba secciones: con A y B el avance superaba el 100 %.
        $scheduledCourses = TeacherAssignment::where('academic_period_id', $this->activePeriod->id)
            ->where('status', 'active')
            ->where('shift_id', $this->filterShiftId)
            ->whereHas('schedules')
            ->whereHas('didacticUnit', function ($q) {
                $q->where('semester', $this->filterCycleId)
                    ->where('status', 'active')
                    ->whereHas('module.studyPlan', function ($q2) {
                        $q2->where('career_id', $this->selectedCareerId);
                    });
            })->distinct()->count('didactic_unit_id');

        return [
            'total_courses' => $totalCourses,
            'scheduled_courses' => $scheduledCourses,
            'percentage' => $totalCourses > 0 ? round(($scheduledCourses / $totalCourses) * 100) : 0
        ];
    }

    /**
     * Generar reporte y redireccionar
     */
    public function generateReport()
    {
        if (!$this->canExport) {
            $this->dispatch('swal', [
                'icon' => 'warning',
                'title' => 'Datos incompletos',
                'text' => 'Complete todos los campos requeridos.'
            ]);
            return;
        }

        // Construir parámetros
        $params = [
            'type' => $this->exportType,
            'format' => $this->exportFormat,
            'period_id' => $this->activePeriod->id,
        ];

        // Parámetros según tipo
        if ($this->exportType === 'teacher') {
            $params['id'] = $this->exportTeacherId;
        } elseif ($this->exportType === 'career') {
            $params['id'] = $this->exportCareerId;
            $params['semester'] = $this->exportSemester;
            $params['shift_id'] = $this->exportShiftId;
        }

        // Generar URL
        $url = route('academic-process.schedules.export', $params);

        // Cerrar modal
        $this->showExportModal = false;

        // Redireccionar a descarga
        // return redirect()->route('academic-process.schedules.export', $params);
        $this->js("window.open('$url', '_blank');");
    }


    /**
     * Elimina TODOS los horarios del semestre seleccionado en la vista consolidada.
     * Actúa como un reseteo masivo de la programación horaria para ese ciclo/turno/programa.
     */
    /**
     * [NUEVO] Secciones activas del programa + semestre + turno de la vista consolidada
     * (null si faltan filtros o no hay período activo).
     */
    private function consolidatedAssignmentIds(): ?Collection
    {
        if (!$this->selectedCareerId || !$this->filterCycleId || !$this->filterShiftId || !$this->activePeriod) {
            return null;
        }

        return TeacherAssignment::where('academic_period_id', $this->activePeriod->id)
            ->where('status', 'active') // [CORREGIDO] no borrar bloques de secciones suspendidas/concluidas
            ->where('shift_id', $this->filterShiftId)
            ->whereHas('didacticUnit', function ($q) {
                $q->where('semester', $this->filterCycleId)
                    ->whereHas('module.studyPlan', fn($q2) => $q2->where('career_id', $this->selectedCareerId));
            })
            ->pluck('id');
    }

    /**
     * [NUEVO] Confirmación (SweetAlert) indicando cuántos bloques se eliminarán.
     */
    public function confirmResetSemesterSchedules()
    {
        $this->authorize('gestionar-horarios');

        $assignmentIds = $this->consolidatedAssignmentIds();
        $count = $assignmentIds ? Schedule::whereIn('teacher_assignment_id', $assignmentIds)->count() : 0;

        if ($count === 0) {
            $this->dispatch('swal', [
                'icon'  => 'info',
                'title' => 'Sin horarios',
                'text'  => 'No hay bloques horarios para los filtros seleccionados.'
            ]);
            return;
        }

        $this->dispatch('swal:confirm', [
            'title' => '¿Reiniciar el horario del semestre?',
            'text' => "Se eliminarán {$count} bloques del semestre {$this->filterCycleId} en este turno. Esta acción no se puede deshacer.",
            'icon' => 'warning',
            'confirmButtonText' => 'Sí, eliminar',
            'id' => null,
            'onConfirmed' => 'resetSemesterSchedules',
        ]);
    }

    // $id no se usa: el layout siempre envía { id } en los eventos de swal:confirm
    #[On('resetSemesterSchedules')]
    public function resetSemesterSchedules($id = null)
    {
        $this->authorize('gestionar-horarios'); // [NUEVO]

        $assignmentIds = $this->consolidatedAssignmentIds();

        // Validar que los filtros necesarios estén seleccionados
        if ($assignmentIds === null) {
            $this->dispatch('swal', [
                'icon'  => 'error',
                'title' => 'Filtros incompletos',
                'text'  => 'Seleccione Programa, Semestre y Turno antes de reiniciar.'
            ]);
            return;
        }

        if ($assignmentIds->isEmpty()) {
            $this->dispatch('swal', [
                'icon'  => 'info',
                'title' => 'Sin horarios',
                'text'  => 'No se encontraron asignaciones para los filtros seleccionados.'
            ]);
            return;
        }

        // Eliminar todos los horarios asociados a esas asignaciones
        $deleted = Schedule::whereIn('teacher_assignment_id', $assignmentIds)->delete();

        // Refrescar la vista consolidada (se recalcula automáticamente porque consolidatedSchedule es computada)
        $this->dispatch('swal', [
            'icon'  => 'success',
            'title' => 'Horarios eliminados',
            'text'  => "Se eliminaron {$deleted} bloques horarios del semestre {$this->filterCycleId}."
        ]);
    }

    public function render()
    {
        return view('livewire.pages.academic-process.schedules.schedule-manager');
    }
}
