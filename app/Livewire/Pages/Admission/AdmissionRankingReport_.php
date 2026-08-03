<?php

namespace App\Livewire\Pages\Admission;

use App\Exports\AdmissionRankingExport;
use App\Models\AdmissionModality;
use App\Models\AdmissionOffering;
use App\Models\Applicant;
use App\Models\Career;
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

    public Collection $modalities;
    public Collection $careers;

    // Preview de ranking en pantalla
    public array $rankingPreview = [];
    public int   $vacancies      = 0;

    public function mount()
    {
        $this->modalities = AdmissionModality::where('is_active', true)->orderBy('name')->get();
        $this->careers    = Career::where('status', 'active')->orderBy('name')->get();
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

    public function loadPreview(): void
    {
        if (!$this->modalityId || !$this->careerId) {
            $this->rankingPreview = [];
            $this->vacancies      = 0;
            return;
        }

        $this->vacancies = AdmissionOffering::where('career_id', $this->careerId)
            ->where('is_active', true)
            ->sum('vacancies');

        $applicants = Applicant::with(['user'])
            ->where('admission_modality_id', $this->modalityId)
            ->whereHas('admissionOffering', fn($q) => $q->where('career_id', $this->careerId))
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

        if (!$this->modalityId || !$this->careerId) {
            $this->dispatch('swal', [
                'icon'  => 'warning',
                'title' => 'Atención',
                'text'  => 'Seleccione la modalidad y el programa de estudios.',
            ]);
            return;
        }

        $career   = Career::find($this->careerId);
        $filename = 'ranking-' . str($career->name ?? 'programa')->slug() . '-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(
            new AdmissionRankingExport((int) $this->modalityId, (int) $this->careerId),
            $filename
        );
    }

    public function render()
    {
        return view('livewire.pages.admission.admission-ranking-report');
    }
}
