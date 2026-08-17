<?php

namespace App\Livewire\Pages\Wellness\Forms;

use App\Models\Wellness\SurveyQuestion;
use App\Models\Wellness\SurveyQuestionOption;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WellnessQuestionManager extends Component
{
    public $sectionId;
    public $questions = [];

    protected $listeners = ['refreshQuestions' => 'loadQuestions'];

    public function mount($sectionId)
    {
        $this->sectionId = $sectionId;
        $this->loadQuestions();
    }

    public function loadQuestions()
    {
        $this->questions = SurveyQuestion::where('survey_section_id', $this->sectionId)
            ->with('options')
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function addQuestion()
    {
        $maxOrder = SurveyQuestion::where('survey_section_id', $this->sectionId)->max('sort_order') ?? 0;
        $question = SurveyQuestion::create([
            'survey_section_id' => $this->sectionId,
            'question_text' => 'Nueva pregunta',
            'question_type' => 'text_short',
            'sort_order' => $maxOrder + 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
        $this->loadQuestions();
        $this->dispatch('questionAdded');
    }

    public function updateQuestion($questionId, $field, $value)
    {
        $question = SurveyQuestion::findOrFail($questionId);
        $question->$field = $value;
        $question->updated_by = Auth::id();
        $question->save();
        $this->loadQuestions();
    }

    public function deleteQuestion($questionId)
    {
        $question = SurveyQuestion::findOrFail($questionId);
        $question->delete();
        $this->loadQuestions();
        $this->dispatch('questionDeleted');
    }

    public function addOption($questionId)
    {
        $question = SurveyQuestion::findOrFail($questionId);
        $maxOrder = SurveyQuestionOption::where('survey_question_id', $questionId)->max('sort_order') ?? 0;
        SurveyQuestionOption::create([
            'survey_question_id' => $questionId,
            'option_value' => 'Nueva opción',
            'sort_order' => $maxOrder + 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
        $this->loadQuestions();
    }

    public function updateOption($optionId, $field, $value)
    {
        $option = SurveyQuestionOption::findOrFail($optionId);
        $option->$field = $value;
        $option->updated_by = Auth::id();
        $option->save();
        $this->loadQuestions();
    }

    public function deleteOption($optionId)
    {
        $option = SurveyQuestionOption::findOrFail($optionId);
        $option->delete();
        $this->loadQuestions();
    }

    public function render()
    {
        return view('livewire.pages.wellness.forms.wellness-question-manager');
    }
}
