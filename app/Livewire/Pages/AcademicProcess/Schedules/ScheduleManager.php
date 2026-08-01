<?php

namespace App\Livewire\Pages\AcademicProcess\Schedules;

use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\ClassroomResource;
use App\Models\DidacticUnit;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Component;

#[Layout('layouts.app')]
class ScheduleManager extends Component
{
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

    public function selectUnit($id, $name, $semester)
    {
        $this->selectedUnitId = $id;
        $this->searchUnit = $name;
        $this->selectedUnitName = "Semestre $semester - $name";
        $this->unitResults = [];
        $this->loadAssignments();
    }

    private function resetUnitSearch()
    {
        $this->selectedUnitId = '';
        $this->searchUnit = '';
        $this->selectedUnitName = '';
        $this->unitResults = [];
        $this->selectedAssignmentId = '';
        $this->selectedAssignment = null;
        $this->currentSchedules = collect();
    }

    public function loadAssignments()
    {
        if ($this->selectedUnitId && $this->activePeriod) {
            $this->sectionAssignments = TeacherAssignment::with(['teacher.user', 'shift'])
                ->where('academic_period_id', $this->activePeriod->id)
                ->where('didactic_unit_id', $this->selectedUnitId)
                ->where('status', 'active')
                ->get();

            if ($this->sectionAssignments->count() === 1) {
                $this->selectedAssignmentId = $this->sectionAssignments->first()->id;
                $this->updatedSelectedAssignmentId($this->selectedAssignmentId);
            }
        }
    }

    public function updatedSelectedAssignmentId($value)
    {
        if ($value) {
            $this->selectedAssignment = TeacherAssignment::with(['teacher', 'didacticUnit'])->find($value);
            $this->loadSchedules();
        } else {
            $this->selectedAssignment = null;
            $this->currentSchedules = collect();
        }
    }

    public function loadSchedules()
    {
        if ($this->selectedAssignment) {
            $this->currentSchedules = $this->selectedAssignment->schedules()
                ->with('classroomResource')
                ->orderByRaw("FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->orderBy('start_time')
                ->get();
        }
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

    public function addSchedule()
    {
        $this->validate([
            'day_of_week'             => 'required',
            'start_time'              => 'required|date_format:H:i',
            'end_time'                => 'required|date_format:H:i|after:start_time',
            'classroom_resource_id'   => 'nullable|exists:classroom_resources,id',
        ]);

        // 1. Verificar conflicto intra-asignatura (lógica existente)
        $conflict = $this->currentSchedules->where('day_of_week', $this->day_of_week)
            ->filter(function ($s) {
                return ($this->start_time < $s->end_time->format('H:i')) &&
                    ($this->end_time > $s->start_time->format('H:i'));
            })->isNotEmpty();

        if ($conflict) {
            $this->dispatch('swal', [
                'icon'  => 'error',
                'title' => 'Conflicto',
                'text'  => 'Ya existe un bloque horario para esta sección en ese día y rango.'
            ]);
            return;
        }

        // 2. Verificar cruces de DOCENTE en otras asignaciones dentro del mismo periodo
        // 2. Verificar cruces de DOCENTE en otras asignaciones (excluyendo la actual)
        $teacherId = $this->selectedAssignment->teacher_id;
        $teacherConflict = Schedule::where('teacher_assignment_id', '!=', $this->selectedAssignmentId) // ← Exclusión clave
            ->whereHas('teacherAssignment', function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId)
                    ->where('academic_period_id', $this->activePeriod->id)
                    ->where('status', 'active');
            })
            ->where('day_of_week', $this->day_of_week)
            ->where(function ($q) {
                $q->where('start_time', '<', $this->end_time)
                    ->where('end_time', '>', $this->start_time);
            })
            ->exists();

        if ($teacherConflict) {
            $this->dispatch('swal', [
                'icon'  => 'error',
                'title' => 'Conflicto de docente',
                'text'  => 'El profesor ya tiene otra clase programada en ese día y rango horario.'
            ]);
            return;
        }

        // 3. Verificar cruces de AULA (solo si se seleccionó una)
        if ($this->classroom_resource_id) {
            $classroomConflict = Schedule::where('classroom_resource_id', $this->classroom_resource_id)
                ->where('day_of_week', $this->day_of_week)
                ->where(function ($q) {
                    $q->where('start_time', '<', $this->end_time)
                        ->where('end_time', '>', $this->start_time);
                })
                ->exists();

            if ($classroomConflict) {
                $this->dispatch('swal', [
                    'icon'  => 'error',
                    'title' => 'Conflicto de aula',
                    'text'  => 'El aula ya está ocupada en ese día y rango horario por otra sección.'
                ]);
                return;
            }
        }

        // 4. Crear el horario
        Schedule::create([
            'teacher_assignment_id' => $this->selectedAssignmentId,
            'day_of_week'           => $this->day_of_week,
            'start_time'            => $this->start_time,
            'end_time'              => $this->end_time,
            'classroom_resource_id' => $this->classroom_resource_id ?: null,
        ]);

        $this->loadSchedules();
        $this->dispatch('swal', [
            'icon'  => 'success',
            'title' => 'Agregado',
            'text'  => 'Bloque horario registrado correctamente.'
        ]);
    }

    public function deleteSchedule($id)
    {
        Schedule::find($id)?->delete();
        $this->loadSchedules();
        $this->dispatch('swal', [
            'icon' => 'success',
            'title' => 'Eliminado',
            'text' => 'Bloque eliminado.'
        ]);
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
