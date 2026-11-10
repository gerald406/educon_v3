<?php

namespace App\Livewire\Pages\Teacher;

use App\Models\AcademicPeriod;
use App\Services\PedagogicalHourService;
use App\Services\TeacherPersonalScheduleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * [NUEVO] "Mi Horario": horario personal del docente autenticado en el período activo.
 *
 * Solo consulta. Los datos vienen de la carga académica y de la gestión de horarios
 * (módulos administrativos); aquí el docente los revisa y descarga el Excel oficial.
 * No recibe ids del navegador: siempre usa Auth::user()->teacher.
 */
#[Layout('layouts.app')]
class MySchedule extends Component
{
    public function render(TeacherPersonalScheduleService $service)
    {
        $teacher = Auth::user()->teacher;
        $period = AcademicPeriod::where('status', 'active')->first();
        $schedule = $teacher && $period ? $service->build($teacher, $period) : null;

        // Jornadas a mostrar: las que tienen bloques; si no hay ninguno, todas
        $groups = PedagogicalHourService::GROUPS;
        if ($schedule && $schedule['groups_with_blocks'] !== []) {
            $groups = array_intersect_key($groups, array_flip($schedule['groups_with_blocks']));
        }

        return view('livewire.pages.teacher.my-schedule', [
            'teacher' => $teacher,
            'period' => $period,
            'schedule' => $schedule,
            'groups' => $groups,
            'dayLabels' => TeacherPersonalScheduleService::DAY_LABELS,
        ]);
    }
}
