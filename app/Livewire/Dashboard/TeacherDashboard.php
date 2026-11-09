<?php

namespace App\Livewire\Dashboard;

use App\Models\AcademicPeriod;
use App\Models\Announcement;
use App\Models\Syllabus;
use App\Models\TeacherAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TeacherDashboard extends Component
{
    public $assignments;
    public $pendingSyllabiCount = 0;
    public $totalHours = 0;
    public ?AcademicPeriod $activePeriod = null;
    public Collection $announcements; // <-- [NUEVO]

    public function mount()
    {
        $this->assignments = collect();
        $this->activePeriod = AcademicPeriod::where('status', 'active')->first();
        $teacher = Auth::user()->teacher;

        if ($teacher && $this->activePeriod) {
            // Cargar asignaciones
            $this->assignments = TeacherAssignment::where('teacher_id', $teacher->id)
                ->where('academic_period_id', $this->activePeriod->id)
                ->with(['didacticUnit', 'syllabus', 'shift'])
                ->get();
                
            // Contar sílabos pendientes
            // [CORREGIDO] 'pending_approval' ya no existe; se cuentan los no aprobados:
            // sin sílabo, borrador, enviado (en revisión) u observado
            $this->pendingSyllabiCount = $this->assignments->filter(function($assignment) {
                return in_array($assignment->syllabus?->status, [null, 'draft', 'submitted', 'observed'], true);
            })->count();
            
            // Calcular horas totales (usando la lógica corregida)
            $this->totalHours = $this->assignments->sum(fn($a) => $a->didacticUnit->weekly_hours ?? 0);
        }

        // --- [NUEVA LÓGICA] ---
        $this->announcements = Announcement::where('status', 'published')
            ->where('publish_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                      ->orWhere('expiration_date', '>', now());
            })
            ->where(function ($query) {
                $query->where('target_audience', 'all')
                      ->orWhere('target_audience', 'teachers');
            })
            ->orderBy('publish_date', 'desc')
            ->take(5)
            ->get();
    }
    
    public function render()
    {
        return view('livewire.dashboard.teacher-dashboard');
    }
}