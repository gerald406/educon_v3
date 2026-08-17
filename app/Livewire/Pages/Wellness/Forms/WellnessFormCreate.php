<?php

namespace App\Livewire\Pages\Wellness\Forms;

use App\Models\Wellness\SurveyForm;
use App\Models\Wellness\SurveySection;
use App\Models\Wellness\SurveyQuestion;
use App\Models\Wellness\SurveyQuestionOption;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class WellnessFormCreate extends Component
{
    public $form = [
        'code' => '',
        'name' => '',
        'description' => '',
        'form_type' => 'encuesta',
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => null,
        'allow_multiple_responses' => false,
        'is_mandatory' => false,
        'target_audience' => 'all',
    ];

    public $sections = [];

    protected $rules = [
        'form.code' => 'required|string|max:50|unique:survey_forms,code',
        'form.name' => 'required|string|max:200',
        'form.form_type' => 'required|in:encuesta,cuestionario,ficha_socioeconomica,diagnostico,evaluacion,otro',
        'form.starts_at' => 'nullable|date',
        'form.ends_at' => 'nullable|date|after_or_equal:form.starts_at',
        'form.is_active' => 'boolean',
        'form.is_mandatory' => 'boolean',
        'form.allow_multiple_responses' => 'boolean',
        'form.target_audience' => 'required|in:all,students,specific_careers,specific_periods',
        // Validaciones dinámicas de secciones y preguntas
        'sections.*.name' => 'required|string|max:150',
        'sections.*.questions.*.question_text' => 'required|string',
        'sections.*.questions.*.question_type' => 'required|string',
    ];

    protected $messages = [
        'sections.*.name.required' => 'El nombre de la sección es obligatorio.',
        'sections.*.questions.*.question_text.required' => 'El texto de la pregunta es obligatorio.',
    ];

    public function mount($id = null)
    {
        $this->addSection();
    }

    public function addSection()
    {
        $this->sections[] = [
            'name' => '',
            'description' => '',
            'sort_order' => count($this->sections) + 1,
            'is_collapsible' => false,
            'questions' => [],
        ];
    }

    public function removeSection($index)
    {
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
        foreach ($this->sections as $i => &$section) {
            $section['sort_order'] = $i + 1;
        }
    }

    public function addQuestion($sectionIndex)
    {
        $this->sections[$sectionIndex]['questions'][] = [
            'question_text' => '',
            'help_text' => '',
            'question_type' => 'text_short',
            'is_required' => false,
            'sort_order' => count($this->sections[$sectionIndex]['questions']) + 1,
            'validation_rules' => null, // Debe guardarse como json o array
            'default_value' => '',
            'options' => [],
        ];
    }

    public function removeQuestion($sectionIndex, $questionIndex)
    {
        unset($this->sections[$sectionIndex]['questions'][$questionIndex]);
        $this->sections[$sectionIndex]['questions'] = array_values($this->sections[$sectionIndex]['questions']);
        foreach ($this->sections[$sectionIndex]['questions'] as $i => &$question) {
            $question['sort_order'] = $i + 1;
        }
    }

    public function addOption($sectionIndex, $questionIndex)
    {
        $this->sections[$sectionIndex]['questions'][$questionIndex]['options'][] = [
            'option_value' => '',
            'sort_order' => count($this->sections[$sectionIndex]['questions'][$questionIndex]['options']) + 1,
        ];
    }

    public function removeOption($sectionIndex, $questionIndex, $optionIndex)
    {
        unset($this->sections[$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]);
        $this->sections[$sectionIndex]['questions'][$questionIndex]['options'] = array_values($this->sections[$sectionIndex]['questions'][$questionIndex]['options']);
        foreach ($this->sections[$sectionIndex]['questions'][$questionIndex]['options'] as $i => &$option) {
            $option['sort_order'] = $i + 1;
        }
    }

    public function save()
    {
        $this->validate();

        $hasQuestions = false;
        foreach ($this->sections as $section) {
            if (!empty($section['questions'])) {
                $hasQuestions = true;
                break;
            }
        }
        if (!$hasQuestions) {
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Estructura vacía', 'text' => 'Debe agregar al menos una pregunta en una sección.']);
            return;
        }

        foreach ($this->sections as $section) {
            foreach ($section['questions'] as $question) {
                if (in_array($question['question_type'], ['single_choice', 'multiple_choice', 'dropdown']) && empty($question['options'])) {
                    $this->dispatch('swal', ['icon' => 'error', 'title' => 'Opciones faltantes', 'text' => 'La pregunta de selección "' . $question['question_text'] . '" debe tener al menos una opción.']);
                    return;
                }
            }
        }

        // Transacción para garantizar atomicidad
        DB::beginTransaction();
        try {
            $institutionId = Auth::user()->institution_id ?? 1;

            $surveyForm = SurveyForm::create([
                'institution_id' => $institutionId,
                'code' => $this->form['code'],
                'name' => $this->form['name'],
                'description' => $this->form['description'],
                'form_type' => $this->form['form_type'],
                'is_active' => $this->form['is_active'],
                'starts_at' => $this->form['starts_at'],
                'ends_at' => $this->form['ends_at'],
                'allow_multiple_responses' => $this->form['allow_multiple_responses'],
                'is_mandatory' => $this->form['is_mandatory'],
                'target_audience' => $this->form['target_audience'],
                'created_by' => Auth::id(),
            ]);

            foreach ($this->sections as $sectionData) {
                $section = SurveySection::create([
                    'survey_form_id' => $surveyForm->id,
                    'name' => $sectionData['name'],
                    'description' => $sectionData['description'],
                    'sort_order' => $sectionData['sort_order'],
                    'is_collapsible' => $sectionData['is_collapsible'],
                    'created_by' => Auth::id(),
                ]);

                foreach ($sectionData['questions'] as $questionData) {
                    $question = SurveyQuestion::create([
                        'survey_section_id' => $section->id,
                        'question_text' => $questionData['question_text'],
                        'help_text' => $questionData['help_text'],
                        'question_type' => $questionData['question_type'],
                        'is_required' => $questionData['is_required'],
                        'sort_order' => $questionData['sort_order'],
                        // Asegurar que si está vacío sea null
                        'validation_rules' => !empty($questionData['validation_rules']) ? json_decode($questionData['validation_rules'], true) : null,
                        'default_value' => $questionData['default_value'],
                        'created_by' => Auth::id(),
                    ]);

                    if (in_array($questionData['question_type'], ['single_choice', 'multiple_choice', 'dropdown'])) {
                        foreach ($questionData['options'] as $optionData) {
                            SurveyQuestionOption::create([
                                'survey_question_id' => $question->id,
                                'option_value' => $optionData['option_value'],
                                'sort_order' => $optionData['sort_order'],
                                'created_by' => Auth::id(),
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            $this->dispatch('swal', ['icon' => 'success', 'title' => '¡Formulario creado!', 'text' => 'El formulario se ha creado correctamente.']);
            return redirect()->route('wellness.forms.index');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'Hubo un error al guardar: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.pages.wellness.forms.wellness-form-create')->layout('layouts.app');
    }
}
