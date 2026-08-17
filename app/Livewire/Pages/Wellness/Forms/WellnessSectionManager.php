<?php

namespace App\Livewire\Pages\Wellness\Forms;

use App\Models\Wellness\SurveySection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WellnessSectionManager extends Component
{
    public $formId;
    public $sections = [];

    protected $listeners = ['refreshSections' => 'loadSections'];

    public function mount($formId)
    {
        $this->formId = $formId;
        $this->loadSections();
    }

    public function loadSections()
    {
        $this->sections = SurveySection::where('survey_form_id', $this->formId)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function addSection()
    {
        $maxOrder = SurveySection::where('survey_form_id', $this->formId)->max('sort_order') ?? 0;
        SurveySection::create([
            'survey_form_id' => $this->formId,
            'name' => 'Nueva Sección',
            'sort_order' => $maxOrder + 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
        $this->loadSections();
        $this->dispatch('sectionAdded');
    }

    public function updateSection($sectionId, $field, $value)
    {
        $section = SurveySection::findOrFail($sectionId);
        $section->$field = $value;
        $section->updated_by = Auth::id();
        $section->save();
        $this->loadSections();
    }

    public function deleteSection($sectionId)
    {
        $section = SurveySection::findOrFail($sectionId);
        $section->delete();
        $this->loadSections();
        $this->dispatch('sectionDeleted');
    }

    public function render()
    {
        return view('livewire.pages.wellness.forms.wellness-section-manager');
    }
}
