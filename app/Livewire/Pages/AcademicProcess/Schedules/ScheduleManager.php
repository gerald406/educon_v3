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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Component;

#[Layout('layouts.app')]
class ScheduleManager extends Component
{
    use AuthorizesRequests; // [NUEVO]

    // [NUEVO] Días programables (orden de la grilla)
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    // ==========================================
    // PROPIEDADES - GESTIÓN DE HORARIOS
    // ==========================================

    public ?AcademicPeriod $activePeriod = null;

    // Filtros de selección
    public $selectedCareerId = '';
    public $searchUnit = '';
    public $unitResults = [];
    public $selectedUnitId = '';
    public $selectedUnitName = '';
    public $selectedAssignmentId = '';
    public ?TeacherAssignment $selectedAssignment = null;

    // Formulario de horario
    public $classroom_resource_id = '';
    public $day_of_week = 'monday';
    public $start_time = '';
    public $end_time = '';
    public $editingScheduleId = null; // [NUEVO] Bloque en edición

    // Colecciones
    public Collection $careers;
    public Collection $classrooms;
    public Collection $shifts;
    public Collection $teachers;
    public Collection $currentSchedules;
    public Collection $sectionAssignments;

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
    public $viewMode = 'unit'; // 'unit' (actual) o 'consolidated'
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

        $this->currentSchedules = collect();
        $this->sectionAssignments = collect();
    }

    // ==========================================
    // GESTIÓN DE HORARIOS (Sin cambios)
    // ==========================================

    public function updatedSelectedCareerId()
    {
        $this->resetUnitSearch();
    }

    public function updatedSearchUnit($value)
    {
        // [CORREGIDO] Al escribir otro curso se descarta la selección anterior.
        // Antes el curso/sección previos seguían seleccionados y los bloques
        // se registraban (o eliminaban) en la sección equivocada.
        $this->clearSelection();

        if (strlen($value) < 2 || !$this->selectedCareerId) {
            $this->unitResults = [];
            return;
        }

        $this->unitResults = DidacticUnit::whereHas(
            'module.studyPlan',
            fn($q) => $q->where('career_id', $this->selectedCareerId)
        )
            ->where('name', 'like', '%' . $value . '%')
            ->where('status', 'active')
            ->orderBy('semester')
            ->take(10)
            ->get();
    }

    // [MODIFICADO] Recibe solo el id: el nombre se lee de la BD (los nombres con apóstrofo rompían el wire:click)
    public function selectUnit($id)
    {
        $unit = DidacticUnit::whereHas(
            'module.studyPlan',
            fn($q) => $q->where('career_id', $this->selectedCareerId)
        )->find($id);

        $this->clearSelection();
        $this->unitResults = [];

        if (!$unit) {
            $this->searchUnit = '';
            return;
        }

        $this->selectedUnitId = $unit->id;
        $this->searchUnit = $unit->name;
        $this->selectedUnitName = "Semestre {$unit->semester} - {$unit->name}";
        $this->loadAssignments();
    }

    private function resetUnitSearch()
    {
        $this->searchUnit = '';
        $this->unitResults = [];
        $this->clearSelection();
    }

    /**
     * [NUEVO] Limpia curso, sección, bloques cargados y el formulario.
     */
    private function clearSelection(): void
    {
        $this->selectedUnitId = '';
        $this->selectedUnitName = '';
        $this->selectedAssignmentId = '';
        $this->selectedAssignment = null;
        $this->sectionAssignments = collect();
        $this->currentSchedules = collect();
        $this->resetBlockForm();
    }

    public function loadAssignments()
    {
        // [CORREGIDO] Siempre se parte de cero: la sección del curso anterior no debe quedar seleccionada
        $this->selectedAssignmentId = '';
        $this->selectedAssignment = null;
        $this->sectionAssignments = collect();
        $this->currentSchedules = collect();

        if ($this->selectedUnitId && $this->activePeriod) {
            $this->sectionAssignments = TeacherAssignment::with(['teacher.user', 'shift'])
                ->where('academic_period_id', $this->activePeriod->id)
                ->where('didactic_unit_id', $this->selectedUnitId)
                ->where('status', 'active')
                ->orderBy('section')
                ->get();

            if ($this->sectionAssignments->count() === 1) {
                $this->selectedAssignmentId = $this->sectionAssignments->first()->id;
                $this->updatedSelectedAssignmentId($this->selectedAssignmentId);
            }
        }
    }

    public function updatedSelectedAssignmentId($value)
    {
        $this->resetBlockForm();
        $this->selectedAssignment = $value ? $this->findSelectableAssignment($value) : null;

        if ($this->selectedAssignment) {
            $this->loadSchedules();
        } else {
            $this->selectedAssignmentId = '';
            $this->currentSchedules = collect();
        }
    }

    /**
     * [NUEVO] La sección debe ser del curso seleccionado y del periodo activo.
     */
    private function findSelectableAssignment($id): ?TeacherAssignment
    {
        if (!$this->activePeriod || !$this->selectedUnitId) {
            return null;
        }

        return TeacherAssignment::with(['teacher.user', 'didacticUnit.module.studyPlan', 'shift'])
            ->where('academic_period_id', $this->activePeriod->id)
            ->where('didactic_unit_id', $this->selectedUnitId)
            ->where('status', 'active')
            ->find($id);
    }

    public function loadSchedules()
    {
        if ($this->selectedAssignment) {
            // [MODIFICADO] Orden por día en PHP (FIELD() es exclusivo de MySQL)
            $this->currentSchedules = $this->selectedAssignment->schedules()
                ->with('classroomResource')
                ->get()
                ->sortBy(fn ($s) => sprintf('%d-%s', array_search($s->day_of_week, self::DAYS, true), $s->start_time->format('H:i')))
                ->values();
        }
    }

    /**
     * [NUEVO] Horas semanales ya programadas para la sección (para comparar con las de la UD).
     */
    #[Computed]
    public function scheduledHours(): float
    {
        return round($this->currentSchedules->sum(
            fn ($s) => $s->start_time->diffInMinutes($s->end_time)
        ) / 60, 2);
    }

    public function setPresetTime($range)
    {
        if ($range === 'morning') {
            $this->start_time = '08:00';
            $this->end_time = '13:00';
        } elseif ($range === 'night') {
            $this->start_time = '17:00';
            $this->end_time = '21:30';
        }
    }

    // ==========================================
    // [NUEVO] EDICIÓN DE BLOQUES
    // ==========================================

    /**
     * Carga un bloque existente en el formulario para modificarlo
     * (antes solo se podía eliminar y volver a crear).
     */
    public function editSchedule($id)
    {
        $schedule = $this->currentSchedules->firstWhere('id', (int) $id);
        if (!$schedule) {
            return;
        }

        $this->editingScheduleId = $schedule->id;
        $this->day_of_week = $schedule->day_of_week;
        $this->start_time = $schedule->start_time->format('H:i');
        $this->end_time = $schedule->end_time->format('H:i');
        $this->classroom_resource_id = $schedule->classroom_resource_id ?? '';
        $this->resetValidation();
    }

    public function cancelEdit()
    {
        $this->resetBlockForm();
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

    // ==========================================
    // REGISTRO DE BLOQUES Y VALIDACIÓN DE CRUCES
    // ==========================================

    /**
     * [MODIFICADO] Crea o actualiza un bloque. Valida cruces de la propia sección,
     * del docente, del grupo de estudiantes (programa + semestre + turno + sección) y del aula,
     * siempre dentro del periodo activo.
     */
    public function addSchedule()
    {
        $this->authorize('gestionar-horarios');

        // [CORREGIDO] La sección se vuelve a resolver en el servidor (no se confía en el id del formulario)
        $assignment = $this->selectedAssignmentId ? $this->findSelectableAssignment($this->selectedAssignmentId) : null;
        if (!$assignment) {
            $this->dispatch('swal', [
                'icon'  => 'warning',
                'title' => 'Seleccione una sección',
                'text'  => 'Seleccione el curso y la sección antes de registrar el bloque.'
            ]);
            return;
        }

        $this->validate([
            'day_of_week'           => 'required|in:' . implode(',', self::DAYS),
            'start_time'            => 'required|date_format:H:i',
            'end_time'              => 'required|date_format:H:i|after:start_time',
            'classroom_resource_id' => 'nullable|exists:classroom_resources,id',
        ]);

        $start = TimeCast::normalize($this->start_time);
        $end = TimeCast::normalize($this->end_time);

        $editing = null;
        if ($this->editingScheduleId) {
            $editing = Schedule::where('teacher_assignment_id', $assignment->id)->find($this->editingScheduleId);
            if (!$editing) {
                $this->resetBlockForm();
                return;
            }
        }

        if ($message = $this->findConflict($assignment, $this->day_of_week, $start, $end, $editing?->id)) {
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Cruce de horario', 'text' => $message]);
            return;
        }

        $data = [
            'teacher_assignment_id' => $assignment->id,
            'day_of_week'           => $this->day_of_week,
            'start_time'            => $start,
            'end_time'              => $end,
            'classroom_resource_id' => $this->classroom_resource_id ?: null,
        ];

        $editing ? $editing->update($data) : Schedule::create($data);

        $this->dispatch('swal', [
            'icon'  => 'success',
            'title' => $editing ? 'Actualizado' : 'Agregado',
            'text'  => $editing ? 'Bloque horario actualizado.' : 'Bloque horario registrado correctamente.'
        ]);

        $this->resetBlockForm();
        $this->loadSchedules();
    }

    /**
     * [NUEVO] Devuelve el motivo del cruce o null si el bloque es válido.
     */
    private function findConflict(TeacherAssignment $assignment, string $day, string $start, string $end, ?int $ignoreScheduleId): ?string
    {
        $periodId = $this->activePeriod->id;

        // Bloques del periodo activo que se solapan en el mismo día
        $overlapping = fn () => Schedule::with(['teacherAssignment.didacticUnit', 'classroomResource'])
            ->when($ignoreScheduleId, fn ($q) => $q->where('id', '!=', $ignoreScheduleId))
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
            return 'Esta sección ya tiene un bloque en ese día y rango: ' . $describe($s) . '.';
        }

        // 2. Docente en otra sección
        if ($s = $overlapping()
            ->where('teacher_assignment_id', '!=', $assignment->id)
            ->whereHas('teacherAssignment', fn ($q) => $q->where('teacher_id', $assignment->teacher_id))
            ->first()) {
            return 'El docente ya tiene clase en ese horario: ' . $describe($s) . '.';
        }

        // 3. [NUEVO] Mismo grupo de estudiantes: programa + semestre + turno + sección
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
            return "Los estudiantes del semestre {$unit->semester}, sección {$assignment->section} ya tienen otra clase en ese horario: " . $describe($s) . '.';
        }

        // 4. Aula (solo bloques del periodo activo; antes chocaba también con periodos pasados)
        if ($this->classroom_resource_id && $s = $overlapping()
            ->where('classroom_resource_id', $this->classroom_resource_id)
            ->first()) {
            return 'El aula ' . ($s->classroomResource->name ?? '') . ' ya está ocupada en ese horario: ' . $describe($s) . '.';
        }

        return null;
    }

    public function deleteSchedule($id)
    {
        $this->authorize('gestionar-horarios');

        // [CORREGIDO] Solo bloques de la sección seleccionada
        $deleted = $this->selectedAssignment
            ? Schedule::where('teacher_assignment_id', $this->selectedAssignment->id)->whereKey($id)->delete()
            : 0;

        if ((int) $this->editingScheduleId === (int) $id) {
            $this->resetBlockForm();
        }

        $this->loadSchedules();

        if ($deleted) {
            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Eliminado',
                'text' => 'Bloque eliminado.'
            ]);
        }
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
            return ['total_courses' => 0, 'scheduled_courses' => 0];
        }

        // Total de cursos del semestre
        $totalCourses = DidacticUnit::where('semester', $this->filterCycleId)
            ->where('status', 'active')
            ->whereHas('module.studyPlan', function ($q) {
                $q->where('career_id', $this->selectedCareerId);
            })->count();

        // Cursos que ya tienen asignación docente y al menos un horario en este turno
        $scheduledCourses = TeacherAssignment::where('academic_period_id', $this->activePeriod->id)
            ->where('shift_id', $this->filterShiftId)
            ->whereHas('schedules')
            ->whereHas('didacticUnit', function ($q) {
                $q->where('semester', $this->filterCycleId)
                    ->whereHas('module.studyPlan', function ($q2) {
                        $q2->where('career_id', $this->selectedCareerId);
                    });
            })->count();

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
    public function resetSemesterSchedules()
    {
        $this->authorize('gestionar-horarios'); // [NUEVO]

        // Validar que los filtros necesarios estén seleccionados
        if (!$this->selectedCareerId || !$this->filterCycleId || !$this->filterShiftId || !$this->activePeriod) {
            $this->dispatch('swal', [
                'icon'  => 'error',
                'title' => 'Filtros incompletos',
                'text'  => 'Seleccione Programa, Semestre y Turno antes de reiniciar.'
            ]);
            return;
        }

        // Obtener los IDs de las asignaciones que coinciden con los filtros
        $assignmentIds = TeacherAssignment::where('academic_period_id', $this->activePeriod->id)
            ->where('shift_id', $this->filterShiftId)
            ->whereHas('didacticUnit', function ($q) {
                $q->where('semester', $this->filterCycleId)
                    ->whereHas('module.studyPlan', fn($q2) => $q2->where('career_id', $this->selectedCareerId));
            })
            ->pluck('id');

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
