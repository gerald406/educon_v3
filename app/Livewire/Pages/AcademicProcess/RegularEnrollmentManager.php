<?php

namespace App\Livewire\Pages\AcademicProcess;

use App\Models\AcademicPeriod;
use App\Models\Enrollment;
use App\Models\Registration;
use App\Models\Student;
use App\Services\EnrollmentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RegularEnrollmentManager extends Component
{
    // --- Búsqueda ---
    public $search = '';
    public Collection $searchResults;
    public ?Student $selectedStudent = null;

    // --- Estado Académico ---
    public $activePeriod;
    public $nextSemester = 1;
    public Collection $proposalRegular;
    public Collection $proposalRecovery;

    // --- Formulario ---
    // [MODIFICADO] Ya no se exige comprobante de pago para matricular
    public $notes = '';

    public function mount()
    {
        // Inicialización segura
        $this->searchResults = collect();
        $this->proposalRegular = collect();
        $this->proposalRecovery = collect();

        // 1. Cargar Periodo Activo
        $this->activePeriod = AcademicPeriod::where('status', 'active')->first();

    }

    public function updatedSearch($value)
    {
        if (strlen($value) < 3) {
            $this->searchResults = collect();
            return;
        }

        $this->searchResults = Student::with('user', 'career')
            ->whereHas('user', function ($q) use ($value) {
                $q->where('document_number', 'like', "%$value%")
                    ->orWhere('name', 'like', "%$value%")
                    ->orWhere('lastname', 'like', "%$value%");
            })
            ->take(5)->get();
    }

    public function selectStudent($studentId)
    {
        $this->reset('notes');

        $this->selectedStudent = Student::with('user', 'career', 'studyPlan')->find($studentId);
        $this->search = '';
        $this->searchResults = collect();

        if (!$this->activePeriod) {
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'No hay periodo académico activo (ej. 2025-I).']);
            return;
        }

        // Validar si ya existe matrícula
        $exists = Enrollment::where('student_id', $this->selectedStudent->id)
            ->where('academic_period_id', $this->activePeriod->id)
            ->where('status', 'active') // Solo validamos activas, permitimos si la anterior fue anulada
            ->exists();

        if ($exists) {
            $this->dispatch('swal', ['icon' => 'warning', 'title' => 'Ya Matriculado', 'text' => 'El estudiante ya está matriculado en este periodo.']);
            $this->selectedStudent = null;
            return;
        }

        // Calcular Semestre
        $this->nextSemester = ($this->selectedStudent->current_semester < 6)
            ? $this->selectedStudent->current_semester
            : 6;

        // Obtener Cursos desde el Servicio
        $service = app(EnrollmentService::class);
        $proposal = $service->getEnrollmentProposal(
            $this->selectedStudent,
            $this->nextSemester,
            $this->activePeriod->id
        );

        $this->proposalRegular = $proposal['regular'];
        $this->proposalRecovery = $proposal['recovery'];

        if ($this->proposalRegular->isEmpty() && $this->proposalRecovery->isEmpty()) {
            $this->dispatch('swal', [
                'icon' => 'warning',
                'title' => 'Sin Carga Académica',
                'text' => "El estudiante es apto para el Semestre {$this->nextSemester}, pero NO SE ENCONTRARON SECCIONES programadas en el periodo {$this->activePeriod->code}."
            ]);
        }
    }

    public function confirmEnrollment()
    {
        if ($this->proposalRegular->isEmpty() && $this->proposalRecovery->isEmpty()) {
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Imposible Matricular', 'text' => 'No hay cursos disponibles para inscribir.']);
            return;
        }

        try {
            DB::transaction(function () {
                // A. Crear Cabecera
                // Ajustamos el tipo de matrícula. Si tu BD es varchar(50) usa strings largos.
                $enrollmentType = $this->proposalRecovery->isNotEmpty() ? 'regular_with_recovery' : 'regular';

                $enrollment = Enrollment::create([
                    'student_id' => $this->selectedStudent->id,
                    'academic_period_id' => $this->activePeriod->id,
                    'semester_enrolled' => $this->nextSemester,
                    'enrollment_type' => $enrollmentType,
                    'status' => 'active',
                    'payment_status' => 'paid',
                    'notes' => $this->notes,
                    'registered_by_user_id' => auth()->id(),
                    'enrollment_date' => now(),
                ]);

                // B. Inscribir Cursos
                $allAssignments = $this->proposalRegular->merge($this->proposalRecovery);

                foreach ($allAssignments as $assignment) {
                    Registration::create([
                        'enrollment_id' => $enrollment->id,
                        'teacher_assignment_id' => $assignment->id,
                        'status' => 'enrolled',
                        'registration_type' => 'mandatory',
                        'registration_date' => now(),
                    ]);

                    $assignment->increment('current_enrolled');
                }

                // C. Actualizar Estudiante
                if ($this->selectedStudent->current_semester < $this->nextSemester) {
                    $this->selectedStudent->update(['current_semester' => $this->nextSemester]);
                }
            });

            // Generar URL del PDF
            // ASEGÚRATE QUE 'people.students.enrollment-form' EXISTA EN TU WEB.PHP
            $pdfUrl = route('people.students.enrollment-form', ['student' => $this->selectedStudent->id]);

            $this->dispatch('swal', [
                'icon' => 'success',
                'title' => '¡Matrícula Exitosa!',
                'text' => 'Proceso completado. Se abrirá la ficha de matrícula.',
                'timer' => 2000,
                'showConfirmButton' => false
            ]);

            $this->dispatch('open-pdf', url: $pdfUrl);

            $this->reset('selectedStudent', 'notes', 'proposalRegular', 'proposalRecovery');
        } catch (\Exception $e) {
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()]);
        }
    }

    public function cancelSelection()
    {
        $this->reset('selectedStudent', 'proposalRegular', 'proposalRecovery');
        $this->searchResults = collect(); // Limpiar resultados
    }

    public function render()
    {
        return view('livewire.pages.academic-process.regular-enrollment-manager');
    }
}
