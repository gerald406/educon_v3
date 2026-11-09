<?php

namespace App\Livewire\Pages\Teacher;

use App\Models\LearningSession;
use App\Models\Syllabus;
use App\Models\SyllabusUnit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LearningSessionList extends Component
{
    public Syllabus $syllabus;

    public function mount(Syllabus $syllabus)
    {
        $teacher = Auth::user()->teacher;

        if (!$teacher || $syllabus->teacherAssignment->teacher_id !== $teacher->id) {
            abort(403, 'No tienes permiso para acceder a estas sesiones.');
        }

        if ($syllabus->status !== 'approved') {
            abort(403, 'Solo puedes gestionar sesiones de sílabos aprobados.');
        }

        $this->syllabus = $syllabus;
        $this->generateMissingSessions();
    }

    /**
     * Crea automáticamente los registros en learning_sessions
     * para cada syllabus_unit que aún no tenga uno.
     */
    protected function generateMissingSessions(): void
    {
        // [MODIFICADO] Incluye las semanas 17-18 de recuperación
        $units = SyllabusUnit::ofSyllabus($this->syllabus->id)->get();

        foreach ($units as $unit) {
            // [MODIFICADO] Plantilla centralizada en el modelo
            LearningSession::firstOrCreate(
                ['syllabus_unit_id' => $unit->id],
                LearningSession::defaultAttributes()
            );
        }
    }

    public function render()
    {
        $this->syllabus->load([
            'teacherAssignment.didacticUnit.module.studyPlan.career',
            'teacherAssignment.academicPeriod',
            'teacherAssignment.teacher.user',
            'indicators.units.learningSession',
            'recoveryUnits.learningSession', // [NUEVO]
        ]);

        // Calcular progreso (incluye las semanas de recuperación)
        $allUnits       = $this->syllabus->indicators->flatMap->units
            ->concat($this->syllabus->recoveryUnits);
        $totalUnits     = $allUnits->count();
        $completedUnits = $allUnits->filter(
            fn($u) => in_array($u->learningSession?->status, ['completed', 'executed'])
        )->count();
        $percent = $totalUnits > 0 ? round(($completedUnits / $totalUnits) * 100) : 0;

        return view('livewire.pages.teacher.learning-session-list', [
            'syllabus'       => $this->syllabus,
            'totalUnits'     => $totalUnits,
            'completedUnits' => $completedUnits,
            'percent'        => $percent,
        ]);
    }
}
