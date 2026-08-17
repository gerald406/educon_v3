<?php

namespace App\Livewire\Pages\Wellness\Student;

use App\Models\Career;
use App\Models\Location;
use App\Models\OriginSchool;
use App\Models\Student;
use App\Models\StudyPlan;
use App\Models\Wellness\SurveyAnswer;
use App\Models\Wellness\SurveyForm;
use App\Models\Wellness\SurveyResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class StudentOnboardingWizard extends Component
{
    use WithFileUploads;

    public $step = 1; // 1: Perfil, 2: Ficha Socioeconómica
    public $isExistingStudent = false;

    // ==========================================
    // ESTADO DEL PERFIL (PASO 1)
    // ==========================================
    public $profile = [
        'career_id' => '',
        'study_plan_id' => '',
        'code' => '',
        'phone' => '',
        'address' => '',
        'gender' => '',
        'birthday' => '',
        'ubigeo_birth_id' => '',
        'origin_school_id' => '',
        'school_graduation_year' => '',
    ];

    public $careers = [];
    public $studyPlans = [];

    // --- BUSCADOR UBIGEO ---
    public $ubigeoSearch = '';
    public $ubigeoResults = [];
    public $selectedUbigeoName = '';

    // --- BUSCADOR COLEGIO ---
    public $schoolSearch = '';
    public $schoolResults = [];
    public $selectedSchoolName = '';

    // ==========================================
    // ESTADO DE LA FICHA (PASO 2)
    // ==========================================
    public $surveyForm;
    public $answers = [];

    public function mount()
    {
        $user = Auth::user();
        $student = $user->student;

        // Cargar carreras activas
        $this->careers = Career::where('status', 'active')->orderBy('name')->get();

        if ($student) {
            $this->isExistingStudent = true;
            $this->profile = [
                'career_id' => $student->career_id,
                'study_plan_id' => $student->study_plan_id,
                'code' => $student->code,
                'phone' => $student->phone,
                'address' => $student->address,
                'gender' => $student->gender,
                'birthday' => $student->birthday ? $student->birthday->format('Y-m-d') : '',
                'ubigeo_birth_id' => $student->ubigeo_birth_id,
                'origin_school_id' => $student->origin_school_id,
                'school_graduation_year' => $student->school_graduation_year,
            ];

            if ($student->career_id) {
                $this->studyPlans = StudyPlan::where('career_id', $student->career_id)->where('status', 'active')->get();
            }

            // Hidratar nombres seleccionados para los buscadores predictivos
            if ($student->ubigeo_birth_id) {
                $location = Location::where('iddist', $student->ubigeo_birth_id)->first();
                if ($location) {
                    $this->selectedUbigeoName = $location->nombdep . ' / ' . $location->nombprov . ' / ' . $location->nombdist;
                }
            }
            if ($student->origin_school_id) {
                $school = OriginSchool::find($student->origin_school_id);
                if ($school) {
                    $this->selectedSchoolName = $school->name;
                }
            }
        }

        // Cargar la Ficha Obligatoria
        $this->surveyForm = SurveyForm::with(['sections.questions.options'])
            ->active()
            ->mandatory()
            ->first();

        // Si ya contestó la ficha, redirigir al Dashboard
        if ($student && $this->surveyForm) {
            $hasResponded = SurveyResponse::where('survey_form_id', $this->surveyForm->id)
                ->where('student_id', $student->id)
                ->exists();

            if ($hasResponded) {
                return redirect()->route('dashboard');
            }
        }
    }

    public function updatedProfileCareerId($value)
    {
        $this->studyPlans = StudyPlan::where('career_id', $value)->where('status', 'active')->get();
        $this->profile['study_plan_id'] = '';
    }

    // ==========================================
    // MÉTODOS DE BUSCADORES PREDICTIVOS
    // ==========================================
    public function updatedUbigeoSearch($value)
    {
        if (strlen($value) < 2) {
            $this->ubigeoResults = [];
            return;
        }
        $this->ubigeoResults = Location::where('nombdist', 'like', "%$value%")
            ->orWhere('nombprov', 'like', "%$value%")
            ->take(10)
            ->get()
            ->toArray();
    }

    public function selectUbigeo($id, $name)
    {
        $this->profile['ubigeo_birth_id'] = $id;
        $this->selectedUbigeoName = $name;
        $this->ubigeoSearch = '';
        $this->ubigeoResults = [];
    }

    public function updatedSchoolSearch($value)
    {
        if (strlen($value) < 2) {
            $this->schoolResults = [];
            return;
        }
        $this->schoolResults = OriginSchool::with('location')
            ->where('name', 'like', "%$value%")
            ->take(5)
            ->get();
    }

    public function selectSchool($id, $name)
    {
        $this->profile['origin_school_id'] = $id;
        $this->selectedSchoolName = $name;
        $this->schoolSearch = '';
        $this->schoolResults = [];
    }

    // ==========================================
    // ACCIONES
    // ==========================================
    public function saveProfileAndContinue()
    {
        $rules = [
            'profile.phone' => 'required|string|max:20',
            'profile.address' => 'required|string|max:255',
            'profile.gender' => 'required|in:masculino,femenino',
            'profile.birthday' => 'required|date|before:today',
            'profile.ubigeo_birth_id' => 'required|string|exists:locations,iddist',
            'profile.origin_school_id' => 'required|integer|exists:origin_schools,id',
            'profile.school_graduation_year' => 'required|digits:4|integer|min:1950|max:' . date('Y'),
        ];

        if (!$this->isExistingStudent) {
            $rules['profile.career_id'] = 'required|integer|exists:careers,id';
            $rules['profile.study_plan_id'] = 'required|integer|exists:study_plans,id';
            $rules['profile.code'] = ['required', 'string', 'max:20', Rule::unique('students', 'code')];
        }

        $this->validate($rules);

        DB::beginTransaction();
        try {
            if (!$this->isExistingStudent) {
                Student::create([
                    'user_id' => Auth::id(),
                    'career_id' => $this->profile['career_id'],
                    'study_plan_id' => $this->profile['study_plan_id'],
                    'code' => $this->profile['code'],
                    'current_semester' => 1,
                    'academic_status' => 'regular',
                    'admission_date' => now(),
                    'phone' => $this->profile['phone'],
                    'address' => $this->profile['address'],
                    'gender' => $this->profile['gender'],
                    'birthday' => $this->profile['birthday'],
                    'ubigeo_birth_id' => $this->profile['ubigeo_birth_id'],
                    'origin_school_id' => $this->profile['origin_school_id'],
                    'school_graduation_year' => $this->profile['school_graduation_year'],
                ]);
            } else {
                Auth::user()->student()->update([
                    'phone' => $this->profile['phone'],
                    'address' => $this->profile['address'],
                    'gender' => $this->profile['gender'],
                    'birthday' => $this->profile['birthday'],
                    'ubigeo_birth_id' => $this->profile['ubigeo_birth_id'],
                    'origin_school_id' => $this->profile['origin_school_id'],
                    'school_graduation_year' => $this->profile['school_graduation_year'],
                ]);
            }

            DB::commit();

            if (!$this->surveyForm) {
                return redirect()->route('dashboard');
            }

            $this->step = 2;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'No se pudo guardar el perfil: ' . $e->getMessage()]);
        }
    }

    public function submitSurvey()
    {
        $rules = [];
        $messages = [];

        foreach ($this->surveyForm->sections as $section) {
            foreach ($section->questions as $question) {
                $field = 'answers.' . $question->id;
                $ruleSet = [];

                if ($question->is_required) {
                    $ruleSet[] = 'required';
                    $messages["{$field}.required"] = "La pregunta '{$question->question_text}' es obligatoria.";
                } else {
                    $ruleSet[] = 'nullable';
                }

                if ($question->question_type === 'numeric') {
                    $ruleSet[] = 'numeric';
                    $messages["{$field}.numeric"] = "La respuesta a '{$question->question_text}' debe ser un número.";
                } elseif ($question->question_type === 'date') {
                    $ruleSet[] = 'date';
                } elseif ($question->question_type === 'multiple_choice') {
                    $ruleSet[] = 'array';
                }

                $rules[$field] = $ruleSet;
            }
        }

        $this->validate($rules, $messages);

        DB::beginTransaction();
        try {
            $student = Auth::user()->student;

            $response = SurveyResponse::create([
                'survey_form_id' => $this->surveyForm->id,
                'student_id' => $student->id,
                'academic_period_id' => null,
                'responded_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'status' => 'submitted',
                'is_final' => true,
            ]);

            foreach ($this->surveyForm->sections as $section) {
                foreach ($section->questions as $question) {

                    if (!isset($this->answers[$question->id])) {
                        continue;
                    }

                    $answerData = $this->answers[$question->id];

                    if ($question->question_type === 'multiple_choice' && is_array($answerData)) {
                        foreach ($answerData as $optionId => $isSelected) {
                            if ($isSelected) {
                                SurveyAnswer::create([
                                    'survey_response_id' => $response->id,
                                    'survey_question_id' => $question->id,
                                    'survey_question_option_id' => $optionId,
                                    'value' => null,
                                ]);
                            }
                        }
                    } elseif (in_array($question->question_type, ['single_choice', 'dropdown'])) {
                        SurveyAnswer::create([
                            'survey_response_id' => $response->id,
                            'survey_question_id' => $question->id,
                            'survey_question_option_id' => $answerData,
                            'value' => null,
                        ]);
                    } else {
                        SurveyAnswer::create([
                            'survey_response_id' => $response->id,
                            'survey_question_id' => $question->id,
                            'survey_question_option_id' => null,
                            'value' => $answerData,
                        ]);
                    }
                }
            }

            DB::commit();

            $this->dispatch('swal', ['icon' => 'success', 'title' => '¡Ficha Completada!', 'text' => 'Bienvenido al sistema.']);
            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'No se pudo registrar la encuesta: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.pages.wellness.student.student-onboarding-wizard')
            ->layout('layouts.app');
    }
}
