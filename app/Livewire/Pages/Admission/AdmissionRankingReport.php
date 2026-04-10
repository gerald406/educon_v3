<?php

namespace App\Livewire\Pages\Admission;

use App\Exports\AdmissionRankingExport;
use App\Models\AdmissionModality;
use App\Models\AdmissionOffering;
use App\Models\Applicant;
use App\Models\Career;
use App\Models\Shift;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
class AdmissionRankingReport extends Component
{
    use AuthorizesRequests;

    public $modalityId = '';
    public $careerId   = '';
    public $shiftId    = '';

    public Collection $modalities;
    public Collection $careers;
    public Collection $shifts;

    // Preview de ranking en pantalla
    public array $rankingPreview = [];
    public int   $vacancies      = 0;

    public function mount()
    {
        $this->modalities = AdmissionModality::where('is_active', true)->orderBy('name')->get();
        $this->careers    = Career::where('status', 'active')->orderBy('name')->get();
        $this->shifts     = Shift::where('status', 'active')->orderBy('start_time')->get();
    }

    public function updatedModalityId(): void
    {
        $this->rankingPreview = [];
        $this->loadPreview();
    }

    public function updatedCareerId(): void
    {
        $this->rankingPreview = [];
        $this->loadPreview();
    }

    public function updatedShiftId(): void
    {
        $this->rankingPreview = [];
        $this->loadPreview();
    }

    public function loadPreview(): void
    {
        if (!$this->modalityId || !$this->careerId || !$this->shiftId) {
            $this->rankingPreview = [];
            $this->vacancies      = 0;
            return;
        }

        $this->vacancies = AdmissionOffering::where('career_id', $this->careerId)
            ->where('shift_id', $this->shiftId)
            ->where('is_active', true)
            ->sum('vacancies');

        $applicants = Applicant::with(['user'])
            ->where('admission_modality_id', $this->modalityId)
            ->whereHas('admissionOffering', function ($q) {
                $q->where('career_id', $this->careerId)
                    ->where('shift_id', $this->shiftId);
            })
            ->whereNotNull('exam_score')
            ->orderByDesc('exam_score')
            ->get();

        $this->rankingPreview = $applicants->values()->map(function (Applicant $applicant, int $index) {
            $position = $index + 1;
            return [
                'position'  => $position,
                'dni'       => $applicant->user->document_number,
                'name'      => $applicant->user->lastname . ', ' . $applicant->user->name,
                'score'     => number_format((float) $applicant->exam_score, 2),
                'admitted'  => $this->vacancies > 0 && $position <= $this->vacancies,
            ];
        })->toArray();
    }

    public function exportExcel()
    {
        $this->authorize('gestionar-admision');

        if (!$this->modalityId || !$this->careerId || !$this->shiftId) {
            $this->dispatch('swal', [
                'icon'  => 'warning',
                'title' => 'Atención',
                'text'  => 'Seleccione la modalidad, el programa de estudios y el turno.',
            ]);
            return;
        }

        $career   = Career::find($this->careerId);
        $shift    = Shift::find($this->shiftId);
        $filename = 'ranking-'
            . str($career->name ?? 'programa')->slug() . '-'
            . str($shift->name  ?? 'turno')->slug()    . '-'
            . now()->format('Ymd') . '.xlsx';

        return Excel::download(
            new AdmissionRankingExport(
                (int) $this->modalityId,
                (int) $this->careerId,
                (int) $this->shiftId
            ),
            $filename
        );
    }

    public function render()
    {
        return view('livewire.pages.admission.admission-ranking-report');
    }
}
