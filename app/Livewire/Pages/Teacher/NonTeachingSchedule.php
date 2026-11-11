<?php

namespace App\Livewire\Pages\Teacher;

use App\Models\AcademicPeriod;
use App\Models\NonTeachingPlan;
use App\Models\Teacher;
use App\Services\NonTeachingScheduleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * [NUEVO] "Horario No Lectivo": el docente autenticado registra sus actividades no lectivas
 * del período activo y las ubica en la semana. Las reglas viven en NonTeachingScheduleService.
 *
 * Nunca recibe el docente ni el plan desde el navegador: se resuelven con Auth::user()->teacher
 * y el período activo, y los ids de actividades/bloques se buscan dentro de ese plan.
 */
#[Layout('layouts.app')]
class NonTeachingSchedule extends Component
{
    // Actividad cuyas horas se están ubicando en la grilla
    public $selectedActivityId = null;

    // Formulario de actividad
    public bool $showForm = false;

    public $editingActivityId = null;

    public $activity = '';

    public $sub_activity = '';

    public $hours = '';

    public function mount(NonTeachingScheduleService $service): void
    {
        // Crea el plan del período y deja Preparación de clases con las horas del Anexo 2
        $this->plan($service);
    }

    private function teacher(): ?Teacher
    {
        return Auth::user()->teacher;
    }

    private function period(): ?AcademicPeriod
    {
        return AcademicPeriod::where('status', 'active')->first();
    }

    /** Plan del docente autenticado en el período activo (null si falta alguno de los dos). */
    private function plan(NonTeachingScheduleService $service): ?NonTeachingPlan
    {
        $teacher = $this->teacher();
        $period = $this->period();

        // Sin carga lectiva no se registra horario no lectivo
        if (! $teacher || ! $period || $service->lectiveHours($teacher, $period) === 0) {
            return null;
        }

        return $service->ensurePlan($teacher, $period);
    }

    /** [NUEVO] Copia actividades y bloques del período anterior (solo con el horario vacío). */
    public function copyFromPrevious(NonTeachingScheduleService $service): void
    {
        $plan = $this->plan($service);
        if (! $plan) {
            return;
        }

        [$message, $ok] = $service->copyFromPrevious($plan);
        $this->dispatch('swal', ['icon' => $ok ? 'success' : 'warning', 'title' => $ok ? 'Horario copiado' : 'No se copió', 'text' => $message]);
    }

    private function fail(string $message): void
    {
        $this->dispatch('swal', ['icon' => 'error', 'title' => 'No se pudo guardar', 'text' => $message]);
    }

    // ------------------------------------------------------------------
    // Actividades
    // ------------------------------------------------------------------

    public function newActivity(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editActivity($id, NonTeachingScheduleService $service): void
    {
        $activity = $this->plan($service)?->activities()->find($id);
        if (! $activity) {
            return;
        }

        $this->resetForm();
        $this->editingActivityId = $activity->id;
        $this->activity = $activity->activity;
        $this->sub_activity = (string) $activity->sub_activity;
        $this->hours = $service->formatHours((int) $activity->weekly_minutes);
        $this->showForm = true;
    }

    public function saveActivity(NonTeachingScheduleService $service): void
    {
        $plan = $this->plan($service);
        if (! $plan) {
            return;
        }

        $error = $service->saveActivity(
            $plan,
            $this->editingActivityId ? (int) $this->editingActivityId : null,
            (string) $this->activity,
            (string) $this->sub_activity,
            $this->hours
        );

        if ($error) {
            $this->fail($error);

            return;
        }

        $this->resetForm();
        $this->dispatch('swal', ['icon' => 'success', 'title' => 'Guardado', 'text' => 'Actividad registrada. Ahora ubique sus horas en la semana.']);
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function confirmRemoveActivity($id, NonTeachingScheduleService $service): void
    {
        $activity = $this->plan($service)?->activities()->find($id);
        if (! $activity || $activity->isPreparation()) {
            return;
        }

        $this->dispatch('swal:confirm', [
            'title' => '¿Quitar esta actividad?',
            'text' => $activity->label().': también se quitarán sus horas ubicadas en la semana.',
            'icon' => 'warning',
            'confirmButtonText' => 'Sí, quitar',
            'id' => $activity->id,
            'onConfirmed' => 'removeNonTeachingActivity',
        ]);
    }

    #[On('removeNonTeachingActivity')]
    public function removeActivity($id, NonTeachingScheduleService $service): void
    {
        $plan = $this->plan($service);
        if (! $plan) {
            return;
        }

        if ($error = $service->removeActivity($plan, (int) $id)) {
            $this->fail($error);

            return;
        }

        if ((int) $this->selectedActivityId === (int) $id) {
            $this->selectedActivityId = null;
        }
        if ((int) $this->editingActivityId === (int) $id) {
            $this->resetForm();
        }
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingActivityId = null;
        $this->activity = '';
        $this->sub_activity = '';
        $this->hours = '';
    }

    // ------------------------------------------------------------------
    // Grilla semanal
    // ------------------------------------------------------------------

    public function selectActivity($id, NonTeachingScheduleService $service): void
    {
        $exists = $this->plan($service)?->activities()->whereKey($id)->exists();
        $this->selectedActivityId = $exists ? (int) $id : null;
    }

    public function placeBlock($day, $time, NonTeachingScheduleService $service): void
    {
        $plan = $this->plan($service);
        if (! $plan) {
            return;
        }

        if (! $this->selectedActivityId) {
            $this->dispatch('swal', ['icon' => 'info', 'title' => 'Seleccione una actividad', 'text' => 'Pulse "Ubicar en la semana" en la actividad cuyas horas quiere colocar y luego elija las casillas.']);

            return;
        }

        if ($error = $service->placeBlock($plan, (int) $this->selectedActivityId, (string) $day, (string) $time)) {
            $this->dispatch('swal', ['icon' => 'warning', 'title' => 'No se puede ubicar aquí', 'text' => $error]);
        }
    }

    public function removeBlock($id, NonTeachingScheduleService $service): void
    {
        $plan = $this->plan($service);
        if ($plan) {
            $service->removeBlock($plan, (int) $id);
        }
    }

    public function render(NonTeachingScheduleService $service)
    {
        $teacher = $this->teacher();
        $period = $this->period();

        return view('livewire.pages.teacher.non-teaching-schedule', [
            'teacher' => $teacher,
            'period' => $period,
            'summary' => $teacher && $period ? $service->summary($teacher, $period) : null,
            'canCopy' => $teacher && $period && $service->canCopyFromPrevious($teacher, $period),
            'dayLabels' => NonTeachingScheduleService::DAY_LABELS,
            'service' => $service,
        ]);
    }
}
