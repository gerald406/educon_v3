<?php

namespace App\Livewire\Pages\Wellness\Forms;

use App\Models\Wellness\SurveyForm;
use App\Models\Wellness\SurveySection;
use App\Models\Wellness\SurveyQuestion;
use App\Models\Wellness\SurveyQuestionOption;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WellnessFormEdit extends WellnessFormCreate
{
    public $formId;
    public $isEditing = true;
    public $hasResponses = false;

    public function mount($id = null)
    {
        $this->formId = $id;
        $surveyForm = SurveyForm::with(['sections.questions.options'])->withCount('responses')->findOrFail($id);

        $this->hasResponses = $surveyForm->responses_count > 0;

        $this->form = $surveyForm->only([
            'code',
            'name',
            'description',
            'form_type',
            'is_active',
            'starts_at',
            'ends_at',
            'allow_multiple_responses',
            'is_mandatory',
            'target_audience'
        ]);

        if ($this->form['starts_at']) {
            $this->form['starts_at'] = $surveyForm->starts_at->format('Y-m-d\TH:i');
        }
        if ($this->form['ends_at']) {
            $this->form['ends_at'] = $surveyForm->ends_at->format('Y-m-d\TH:i');
        }

        $this->sections = $surveyForm->sections->map(function ($section) {
            return [
                'id' => $section->id, // Guardamos ID para actualizar
                'name' => $section->name,
                'description' => $section->description,
                'sort_order' => $section->sort_order,
                'is_collapsible' => $section->is_collapsible,
                'questions' => $section->questions->map(function ($question) {
                    return [
                        'id' => $question->id,
                        'question_text' => $question->question_text,
                        'help_text' => $question->help_text,
                        'question_type' => $question->question_type,
                        'is_required' => $question->is_required,
                        'sort_order' => $question->sort_order,
                        'validation_rules' => is_array($question->validation_rules) ? json_encode($question->validation_rules) : '',
                        'default_value' => $question->default_value,
                        'options' => $question->options->map(function ($option) {
                            return [
                                'id' => $option->id,
                                'option_value' => $option->option_value,
                                'sort_order' => $option->sort_order,
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
            ];
        })->toArray();

        if (empty($this->sections) && !$this->hasResponses) {
            $this->addSection();
        }
    }

    public function rules()
    {
        return [
            // Regla dinámica que ignora el ID actual para evitar el error de unique al editar
            'form.code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('survey_forms', 'code')->ignore($this->formId)
            ],
            // Resto de reglas heredadas de la estructura
            'form.name' => 'required|string|max:200',
            'form.form_type' => 'required|in:encuesta,cuestionario,ficha_socioeconomica,diagnostico,evaluacion,otro',
            'form.starts_at' => 'nullable|date',
            'form.ends_at' => 'nullable|date|after_or_equal:form.starts_at',
            'form.is_active' => 'boolean',
            'form.is_mandatory' => 'boolean',
            'form.allow_multiple_responses' => 'boolean',
            'form.target_audience' => 'required|in:all,students,specific_careers,specific_periods',
            'sections.*.name' => 'required|string|max:150',
            'sections.*.questions.*.question_text' => 'required|string',
            'sections.*.questions.*.question_type' => 'required|string',
        ];
    }

    public function save()
    {
        $this->validate();

        DB::beginTransaction();
        try {
            $surveyForm = SurveyForm::findOrFail($this->formId);

            // Actualizar datos básicos (siempre permitido)
            $surveyForm->update([
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
                'updated_by' => Auth::id(),
            ]);

            // Si tiene respuestas, NO modificamos la estructura (secciones/preguntas)
            if ($this->hasResponses) {
                DB::commit();
                $this->dispatch('swal', ['icon' => 'success', 'title' => 'Datos actualizados', 'text' => 'Se actualizaron los datos generales. La estructura no se modificó porque el formulario ya tiene respuestas.']);
                return redirect()->route('wellness.forms.index');
            }

            // --- Lógica estructural solo si NO hay respuestas ---
            // Borrar IDs existentes de la BD para recrearlos limpios
            // (La FK cascade se encarga de questions y options)
            $surveyForm->sections()->forceDelete();

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
            $this->dispatch('swal', ['icon' => 'success', 'title' => '¡Actualizado!', 'text' => 'El formulario se reconstruyó correctamente.']);
            return redirect()->route('wellness.forms.index');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'Hubo un error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.pages.wellness.forms.wellness-form-edit')->layout('layouts.app');
    }
}
