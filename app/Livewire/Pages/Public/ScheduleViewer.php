<?php

namespace App\Livewire\Pages\Public;

use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\Schedule;
use App\Models\Shift;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Carbon\Carbon;

// Usamos el layout 'guest' (invitado) para que no cargue el menú de administrador
#[Layout('layouts.guest')]
class ScheduleViewer extends Component
{
    public ?AcademicPeriod $activePeriod = null;
    public $careers;
    public $shifts;

    // Filtros
    public $selectedCareerId = '';
    public $filterCycleId = '';
    public $filterShiftId = '';
    public $selectedDay = '';

    public function mount()
    {
        $this->activePeriod = AcademicPeriod::where('status', 'active')->first();
        $this->careers = Career::where('status', 'active')->orderBy('name')->get();
        $this->shifts = Shift::where('status', 'active')->orderBy('name')->get();

        // Seleccionar el día actual por defecto (en inglés minúscula para que coincida con la BD)
        $today = strtolower(Carbon::now()->format('l'));
        $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        $this->selectedDay = in_array($today, $validDays) ? $today : 'monday';
    }

    public function setDay($day)
    {
        $this->selectedDay = $day;
    }

    #[Computed]
    public function dailySchedule()
    {
        if (!$this->selectedCareerId || !$this->filterCycleId || !$this->filterShiftId || !$this->activePeriod) {
            return collect();
        }

        // Trae los horarios SOLO del día seleccionado, optimizado para cargar rápido
        return Schedule::with(['teacherAssignment.teacher.user', 'teacherAssignment.didacticUnit', 'classroomResource'])
            ->where('day_of_week', $this->selectedDay)
            ->whereHas('teacherAssignment', function ($q) {
                $q->where('academic_period_id', $this->activePeriod->id)
                    ->where('shift_id', $this->filterShiftId)
                    ->whereHas('didacticUnit', function ($q2) {
                        $q2->where('semester', $this->filterCycleId)
                            ->whereHas('module.studyPlan', fn($q3) => $q3->where('career_id', $this->selectedCareerId));
                    });
            })
            ->orderBy('start_time')
            ->get();
    }

    public function render()
    {
        return view('livewire.pages.public.schedule-viewer');
    }
}
