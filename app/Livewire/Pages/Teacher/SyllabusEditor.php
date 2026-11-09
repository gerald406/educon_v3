<?php

namespace App\Livewire\Pages\Teacher;

use App\Models\Syllabus;
use App\Models\SyllabusIndicator;
use App\Models\SyllabusUnit;
use App\Models\TeacherAssignment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

// AÑADIR junto a los otros use al inicio del archivo
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class SyllabusEditor extends Component
{
    public TeacherAssignment $assignment;
    public Syllabus $syllabus;

    public $activeTab = 'general';
    public $confirmingSubmission = false;

    // AÑADIR después de: public $confirmingSubmission = false;
    public function isEditable(): bool
    {
        return in_array($this->syllabus->status, ['draft', 'observed']);
    }

    /**
     * [NUEVO] Guardián del lado del servidor para toda acción que modifica el sílabo.
     * Lee el estado desde la BD (puede haber cambiado: enviado, aprobado por el coordinador...)
     * porque ocultar los botones en la vista no impide invocar los métodos.
     */
    protected function ensureEditable(): bool
    {
        $currentStatus = Syllabus::whereKey($this->syllabus->id)->value('status');
        $this->syllabus->status = $currentStatus;
        $this->syllabus->syncOriginalAttribute('status');

        if (in_array($currentStatus, ['draft', 'observed'])) {
            return true;
        }

        $this->dispatch('swal', [
            'icon' => 'warning',
            'title' => 'Sílabo bloqueado',
            'text' => 'Este sílabo ya fue enviado o aprobado y no puede modificarse.'
        ]);

        return false;
    }

    // --- DATOS GENERALES ---
    public $study_program;
    public $study_plan;
    public $module_name;
    public $course_name;
    public $credits_info;
    public $total_hours;
    public $weekly_hours_info;
    public $period_name;
    public $academic_cycle;
    public $date_range;
    public $shift_name;
    public $teacher_name;
    public $teacher_email;

    // --- CONTENIDO DEL SÍLABO ---
    public $sumilla;
    public $unit_competence;
    public $course_capacity;
    public $employability_content;
    public $methodology;
    public $environments;
    public $resources;
    public $evaluation_system;
    public $bibliography;
    public $web_sources;

    // --- ESTRUCTURAS COMPLEJAS ---
    public $indicators;
    public $total_weeks_programmed = 0;

    // ✅ LISTENERS para sincronización con CKEditor
    protected $listeners = [
        'updateSumilla' => 'handleSumillaUpdate',
        'updateUnitCompetence' => 'handleUnitCompetenceUpdate',
        'updateCourseCapacity' => 'handleCourseCapacityUpdate',
        'updateEmployability' => 'handleEmployabilityUpdate',
        'updateMethodology' => 'handleMethodologyUpdate',
        'updateEnvironments' => 'handleEnvironmentsUpdate',
        'updateResources' => 'handleResourcesUpdate',
        'updateEvaluation' => 'handleEvaluationUpdate',
        'updateBibliography' => 'handleBibliographyUpdate',
        'updateWebSources' => 'handleWebSourcesUpdate',
    ];

    // REGLAS DE VALIDACIÓN
    // DESPUÉS
    protected $rules = [
        'indicators.*.units.*.name' => 'nullable|string',
        'indicators.*.units.*.content' => 'nullable|string',
        'indicators.*.units.*.learning_outcome' => 'nullable|string',
        'indicators.*.units.*.evaluation_instrument' => 'nullable|string',
        // [MODIFICADO] Los datos generales (Tab I) son de solo lectura: ya no se validan ni guardan aquí
        'sumilla' => 'nullable',
        'unit_competence' => 'nullable',
        'course_capacity' => 'nullable',
        'methodology' => 'nullable',
        'resources' => 'nullable',
        'evaluation_system' => 'nullable',
        'bibliography' => 'nullable',
        'web_sources' => 'nullable',
        'employability_content' => 'nullable'
    ];

    // ========================================
    // HANDLERS para recibir datos de CKEditor
    // ========================================

    public function handleSumillaUpdate($content)
    {
        $this->sumilla = $content;
    }

    public function handleUnitCompetenceUpdate($content)
    {
        $this->unit_competence = $content;
    }

    public function handleCourseCapacityUpdate($content)
    {
        $this->course_capacity = $content;
    }

    public function handleEmployabilityUpdate($content)
    {
        $this->employability_content = $content;
    }

    public function handleMethodologyUpdate($content)
    {
        $this->methodology = $content;
    }

    public function handleEnvironmentsUpdate($content)
    {
        $this->environments = $content;
    }

    public function handleResourcesUpdate($content)
    {
        $this->resources = $content;
    }

    public function handleEvaluationUpdate($content)
    {
        $this->evaluation_system = $content;
    }

    public function handleBibliographyUpdate($content)
    {
        $this->bibliography = $content;
    }

    public function handleWebSourcesUpdate($content)
    {
        $this->web_sources = $content;
    }

    // ========================================
    // INICIALIZACIÓN
    // ========================================

    public function mount(TeacherAssignment $assignment)
    {
        if ($assignment->teacher_id !== auth()->user()->teacher->id) {
            abort(403, 'Acceso denegado.');
        }

        $this->assignment = $assignment->load([
            'didacticUnit.module.studyPlan.career',
            'academicPeriod',
            'teacher.user',
            'shift'
        ]);

        $this->syllabus = Syllabus::firstOrCreate(
            ['teacher_assignment_id' => $assignment->id],
            ['status' => 'draft', 'version' => '1.0']
        );

        $this->loadGeneralData();

        // Carga de campos de texto con valores por defecto
        $this->sumilla = $this->syllabus->sumilla ?? '';
        $this->unit_competence = $this->syllabus->unit_competence ?? '';
        $this->course_capacity = $this->syllabus->course_capacity ?? '';

        // Empleabilidad
        $storedEmployability = $this->syllabus->employability_competencies;
        $this->employability_content = is_array($storedEmployability) ? ($storedEmployability[0] ?? '') : ($storedEmployability ?? '');

        $this->methodology = $this->syllabus->methodology ?? '';
        $this->environments = $this->syllabus->environments ?? '';
        $this->resources = $this->syllabus->resources ?? '';
        $this->evaluation_system = $this->syllabus->evaluation_system ?? '';

        // DESPUÉS
        if (empty($this->evaluation_system)) {
            $this->evaluation_system = '
            <ul>
                <li>El sistema de calificación es vigesimal y la nota mínima aprobatoria para las unidades didácticas es 13.</li>
                <li>Se considera aprobado el módulo, siempre que se haya aprobado todas las unidades didácticas respectivas y la experiencia formativa en situaciones reales de trabajo, de acuerdo al plan de estudios.</li>
                <li>Los estudiantes podrán rendir evaluaciones de recuperación a fin de lograr la aprobación de la unidad didáctica dentro del mismo periodo de estudio, considerando criterios de calidad académica y de acuerdo a los lineamientos establecidos en el reglamento institucional.</li>
                <li>Los estudiantes que tengan unidades didácticas desaprobadas al final del semestre académico podrán volver a matricularse en el siguiente semestre académico o cuando se programe.</li>
                <li>El estudiante que acumulará inasistencias injustificadas en número mayor al 30% del total de horas programadas en la Unidad Didáctica, será desaprobado en forma automática con calificación cero, sin derecho a recuperación.</li>
                <li>La evaluación será permanente durante el desarrollo de las actividades de aprendizaje evaluando las evidencias y/o productos de cada indicador de evaluación.</li>
            </ul>';
        }

        $this->bibliography = $this->syllabus->bibliography ?? '';
        $this->web_sources = $this->syllabus->web_sources ?? '';
        $this->teacher_email = $this->assignment->teacher->user->email;

        $this->loadIndicatorsWithUnits();
    }

    private function loadGeneralData()
    {
        $unit = $this->assignment->didacticUnit;
        $plan = $unit->module->studyPlan;
        $this->study_program = $plan->career->name;
        $this->study_plan    = $plan->name;
        $this->module_name   = $unit->module->name;
        $this->course_name   = $unit->name;
        $this->credits_info  = "{$unit->credits} Créditos";
        $this->total_hours   = $unit->total_hours;
        $calculatedWeekly = ($unit->weekly_hours > 0) ? $unit->weekly_hours : ($unit->total_hours / 16);
        $this->weekly_hours_info = "{$calculatedWeekly} horas semanales";
        $this->period_name   = $this->assignment->academicPeriod->name;
        $this->academic_cycle = $unit->semester;
        $start = \Carbon\Carbon::parse($this->assignment->academicPeriod->start_date)->format('d/m/Y');
        $end   = \Carbon\Carbon::parse($this->assignment->academicPeriod->end_date)->format('d/m/Y');
        $this->date_range = "{$start} – {$end} (18 semanas)";
        $this->shift_name    = $this->assignment->shift->name ?? 'No asignado';
      	$this->teacher_name  = $this->assignment->teacher->user->name . ' ' . $this->assignment->teacher->user->lastname;
    }

    /**
     * ✅ MANTENER COMO COLLECTION (NO usar toArray())
     */
    public function loadIndicatorsWithUnits()
    {
        $this->indicators = $this->syllabus->indicators()
            ->with(['units' => function ($q) {
                $q->orderBy('session_number');
            }])
            ->orderBy('sort_order')
            ->get(); // ← SIN ->toArray()

        $this->calculateTotalWeeks();
    }

    /**
     * ✅ Trabajar con Collection
     */
    public function calculateTotalWeeks()
    {
        $this->total_weeks_programmed = $this->indicators->sum(function ($indicator) {
            return $indicator->units->count();
        });
    }

    // ========================================
    // LÓGICA DE PROGRAMACIÓN (TAB VI)
    // ========================================

    public function addSession($indicatorId)
    {
        if (!$this->ensureEditable()) return;

        // [CORREGIDO] Solo indicadores de este sílabo
        $indicator = SyllabusIndicator::where('syllabus_id', $this->syllabus->id)->find($indicatorId);
        if (!$indicator) return;

        $nextSessionNumber = $this->total_weeks_programmed + 1;

        if ($nextSessionNumber > 18) {
            $this->dispatch('swal', ['icon' => 'warning', 'title' => 'Límite alcanzado', 'text' => 'Ya has programado las 18 semanas.']);
            return;
        }

        $indicator->units()->create([
            'session_number' => $nextSessionNumber,
            'name' => 'Nueva Sesión',
            'content' => '',
            'learning_outcome' => '',
            'evaluation_instrument' => 'Lista de Cotejo',
        ]);

        $this->loadIndicatorsWithUnits();
    }

    /**
     * [NUEVO] Pide confirmación antes de eliminar una semana (borra también su sesión de aprendizaje).
     */
    public function confirmRemoveSession($unitId)
    {
        if (!$this->ensureEditable()) return;

        $this->dispatch('swal:confirm', [
            'title' => '¿Eliminar esta sesión?',
            'text' => 'Se eliminará la semana programada y su sesión de aprendizaje, si existe.',
            'icon' => 'warning',
            'confirmButtonText' => 'Sí, eliminar',
            'id' => $unitId,
            'onConfirmed' => 'removeSession',
        ]);
    }

    // [MODIFICADO] Se ejecuta tras la confirmación (SweetAlert)
    #[On('removeSession')]
    public function removeSession($id)
    {
        if (!$this->ensureEditable()) return;

        // Verificar que la sesión pertenece al sílabo del docente autenticado
        $unit = SyllabusUnit::whereHas('indicator', function ($q) {
            $q->where('syllabus_id', $this->syllabus->id);
        })->find($id);

        if (!$unit) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Acceso denegado',
                'text' => 'No tienes permiso para eliminar esta sesión.'
            ]);
            return;
        }

        $unit->delete();
        $this->reorderSessions();
        $this->loadIndicatorsWithUnits();
    }

    private function reorderSessions()
    {
        $allUnits = SyllabusUnit::whereHas('indicator', function ($q) {
            $q->where('syllabus_id', $this->syllabus->id);
        })->orderBy('id')->get();

        foreach ($allUnits as $index => $unit) {
            $unit->update(['session_number' => $index + 1]);
        }
    }

    // ========================================
    // MÉTODOS DE GUARDADO
    // ========================================

    // [CORREGIDO] Se eliminó saveGeneral(): modificaba datos maestros compartidos
    // (carrera, plan, módulo, unidad didáctica, turno y usuario del docente) desde un sílabo.
    // Los datos generales se muestran en solo lectura y se corrigen en sus propios módulos.

    /**
     * ✅ Guardar Sumilla
     */
    public function saveSumilla()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            $this->syllabus->update(['sumilla' => $this->sumilla]);
            // $this->syllabus->refresh();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Guardado',
                'text' => 'Sumilla actualizada correctamente.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * ✅ Guardar Competencias
     */
    public function saveCompetencies()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            $this->syllabus->update(['unit_competence' => $this->unit_competence]);
            // $this->syllabus->refresh();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Guardado',
                'text' => 'Competencias actualizadas.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    // TAB IV
    public function addIndicator()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        $newIndicator = $this->syllabus->indicators()->create([
            'description' => 'Nuevo indicador',
            'sort_order' => $this->indicators->count() + 1
        ]);

        $this->loadIndicatorsWithUnits();
    }

    /**
     * [NUEVO] Pide confirmación antes de eliminar un indicador
     * (en BD borra en cascada sus semanas programadas y sesiones de aprendizaje).
     */
    public function confirmRemoveIndicator($id)
    {
        if (!$this->ensureEditable()) return;

        $this->dispatch('swal:confirm', [
            'title' => '¿Eliminar este indicador?',
            'text' => 'También se eliminarán todas sus sesiones programadas y sesiones de aprendizaje.',
            'icon' => 'warning',
            'confirmButtonText' => 'Sí, eliminar',
            'id' => $id,
            'onConfirmed' => 'removeIndicator',
        ]);
    }

    // [MODIFICADO] Se ejecuta tras la confirmación (SweetAlert)
    #[On('removeIndicator')]
    public function removeIndicator($id)
    {
        if (!$this->ensureEditable()) return;

        // Verificar que el indicador pertenece al sílabo del docente autenticado
        $indicator = SyllabusIndicator::where('syllabus_id', $this->syllabus->id)
            ->find($id);

        if (!$indicator) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Acceso denegado',
                'text' => 'No tienes permiso para eliminar este indicador.'
            ]);
            return;
        }

        $indicator->delete();
        $this->loadIndicatorsWithUnits();
    }

    /**
     * ✅ Guardar Capacidad e Indicadores
     */
    public function saveCapacityAndIndicators()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            // 1. Guardar la capacidad del curso
            $this->syllabus->update(['course_capacity' => $this->course_capacity]);

            // 2. Guardar cada indicador (trabajando con Collection)
            foreach ($this->indicators as $indicator) {
                if ($indicator->id && isset($indicator->description)) {
                    SyllabusIndicator::where('id', $indicator->id)->update([
                        'description' => $indicator->description
                    ]);
                }
            }

            $this->syllabus->refresh();
            $this->loadIndicatorsWithUnits();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Guardado',
                'text' => 'Capacidad e indicadores guardados correctamente.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    public function saveEmployability()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            $this->syllabus->update([
                'employability_competencies' => [$this->employability_content]
            ]);
            // $this->syllabus->refresh();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Guardado',
                'text' => 'Empleabilidad actualizada.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * ✅ Guardar Programación (trabajando con Collection)
     */
    // DESPUÉS
    public function saveProgramming()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            DB::transaction(function () {
                foreach ($this->indicators as $indicator) {
                    if (!$indicator->units || $indicator->units->isEmpty()) continue;

                    foreach ($indicator->units as $unit) {
                        if (!$unit->id) continue;

                        // Verificar pertenencia antes de actualizar
                        $exists = SyllabusUnit::whereHas('indicator', function ($q) {
                            $q->where('syllabus_id', $this->syllabus->id);
                        })->where('id', $unit->id)->exists();

                        if (!$exists) continue;

                        SyllabusUnit::where('id', $unit->id)->update([
                            'name'                 => $unit->name ?? '',
                            'content'              => $unit->content ?? '',
                            'learning_outcome'     => $unit->learning_outcome ?? '',
                            'evaluation_instrument' => $unit->evaluation_instrument ?? '',
                        ]);
                    }
                }
            });

            // Recargar solo después de que toda la transacción fue exitosa
            $this->loadIndicatorsWithUnits();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Programación Guardada',
                'text' => 'Todas las sesiones han sido registradas correctamente.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * ✅ NUEVO MÉTODO: Actualizar campos individuales de las sesiones en tiempo real
     */
    // DESPUÉS
    public function updateUnitField($unitId, $fieldName, $value)
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            // 1. Validar campo permitido
            $allowedFields = ['name', 'content', 'learning_outcome', 'evaluation_instrument'];

            if (!in_array($fieldName, $allowedFields)) {
                throw new \Exception('Campo no permitido');
            }

            // 2. Verificar pertenencia al sílabo del docente autenticado
            $unit = SyllabusUnit::whereHas('indicator', function ($q) {
                $q->where('syllabus_id', $this->syllabus->id);
            })->find($unitId);

            if (!$unit) {
                throw new \Exception('Acceso denegado');
            }

            // 3. Actualizar solo el campo específico
            $unit->update([$fieldName => $value]);

            // 4. Solo recalcular semanas, NO recargar toda la colección
            $this->calculateTotalWeeks();
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo actualizar: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * ✅ NUEVO: Actualizar descripción de indicador individual
     */
    // DESPUÉS
    public function updateIndicatorDescription($indicatorId, $description)
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            // Verificar que el indicador pertenece al sílabo del docente autenticado
            $indicator = SyllabusIndicator::where('syllabus_id', $this->syllabus->id)
                ->find($indicatorId);

            if (!$indicator) {
                throw new \Exception('Acceso denegado');
            }

            $indicator->update(['description' => $description]);

            // Solo recalcular, NO recargar toda la colección
            $this->calculateTotalWeeks();
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo actualizar: ' . $e->getMessage()
            ]);
        }
    }

    public function saveMethodology()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            $this->syllabus->update(['methodology' => $this->methodology]);
            // $this->syllabus->refresh();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Guardado',
                'text' => 'Metodología actualizada.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    public function saveResources()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            $this->syllabus->update([
                'environments' => $this->environments,
                'resources' => $this->resources
            ]);
            // $this->syllabus->refresh();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Guardado',
                'text' => 'Recursos actualizados.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    public function saveEvaluation()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            $this->syllabus->update(['evaluation_system' => $this->evaluation_system]);
            // $this->syllabus->refresh();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Guardado',
                'text' => 'Evaluación actualizada.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    public function saveSources()
    {
        if (!$this->ensureEditable()) return; // [CORREGIDO] validar estado en el servidor

        try {
            $this->syllabus->update([
                'bibliography' => $this->bibliography,
                'web_sources' => $this->web_sources
            ]);
            // $this->syllabus->refresh();

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => 'Guardado',
                'text' => 'Fuentes actualizadas.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'No se pudo guardar: ' . $e->getMessage()
            ]);
        }
    }

    // DESPUÉS
    public function confirmSubmit()
    {
        // Guarda de seguridad: verificar estado actual en BD (no solo en memoria)
        $currentStatus = Syllabus::find($this->syllabus->id)?->status;

        if (!in_array($currentStatus, ['draft', 'observed'])) {
            $this->dispatch('swal', [
                'icon' => 'warning',
                'title' => 'Acción no permitida',
                'text' => 'Este sílabo no puede ser enviado en su estado actual.'
            ]);
            return;
        }

        if (
            empty(trim(strip_tags($this->sumilla))) ||
            empty(trim(strip_tags($this->methodology))) ||
            empty(trim(strip_tags($this->evaluation_system))) ||
            $this->total_weeks_programmed == 0
        ) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Sílabo Incompleto',
                'text' => 'Debe completar la Sumilla, Metodología, Evaluación y programar al menos una sesión antes de enviar a aprobación.'
            ]);
            return;
        }

        $this->confirmingSubmission = true;
    }

    public function submitSyllabus()
    {
        // [CORREGIDO] validar estado en el servidor (y cerrar el modal si ya no es editable)
        if (!$this->ensureEditable()) {
            $this->confirmingSubmission = false;
            return;
        }

        $this->syllabus->update([
            'status' => 'submitted',
            'submitted_at' => now()
        ]);

        $this->confirmingSubmission = false;
        session()->flash('flash.banner', '¡Sílabo enviado correctamente!');

        return redirect()->route('teacher.my-syllabi');
    }

    public function render()
    {
        return view('livewire.pages.teacher.syllabus-editor');
    }
}
