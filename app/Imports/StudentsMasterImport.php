<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Student;
use App\Models\Career;
use App\Models\StudyPlan;
use App\Models\AcademicPeriod;
use App\Models\Shift;
use App\Models\Enrollment;
use App\Models\Registration;
use App\Models\TeacherAssignment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class StudentsMasterImport implements ToCollection, WithHeadingRow, WithValidation, WithChunkReading
{
    private $careersCache = [];
    private $studyPlansCache = [];
    private $periodsCache = [];
    private $shiftsCache = [];

    public function __construct()
    {
        $this->careersCache = Career::pluck('id', 'code')->toArray();
        $this->studyPlansCache = StudyPlan::pluck('id', 'code')->toArray();
        $this->periodsCache = AcademicPeriod::pluck('id', 'code')->toArray();

        $shifts = Shift::all();
        foreach ($shifts as $shift) {
            $this->shiftsCache[mb_strtoupper(trim($shift->name), 'UTF-8')] = $shift->id;
        }
    }

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            // 1. Agrupar filas por DNI para procesar usuarios y estudiantes una sola vez
            $groupedByDni = $rows->groupBy('dni');

            foreach ($groupedByDni as $dni => $studentRows) {
                // Tomamos la primera fila como referencia para los datos personales
                $baseRow = $studentRows->first();

                // IDs Base
                $careerId = $this->careersCache[$baseRow['cod_carrera']];
                $studyPlanId = $this->studyPlansCache[$baseRow['cod_plan']];
                $periodId = $this->periodsCache[$baseRow['periodo']];
                $semester = (int) $baseRow['semestre'];

                // 2. Crear o actualizar Usuario (Garantizado una sola vez por DNI)
                $user = User::where('document_number', $dni)->first();

                $userData = [
                    'name' => mb_convert_case(trim($baseRow['nombres']), MB_CASE_TITLE, "UTF-8"),
                    'lastname' => mb_convert_case(trim($baseRow['apellidos']), MB_CASE_TITLE, "UTF-8"),
                    'full_name' => mb_convert_case(trim($baseRow['nombres']) . ' ' . trim($baseRow['apellidos']), MB_CASE_TITLE, "UTF-8"),
                    'email' => $dni . '@estudiante.edu.pe',
                ];

                if (!$user) {
                    $userData['document_number'] = $dni;
                    $userData['password'] = Hash::make($dni);
                    $user = User::create($userData);
                } else {
                    $user->update($userData);
                }

                if (!$user->hasRole('Estudiante')) {
                    $user->assignRole('Estudiante');
                }

                // 3. Crear o actualizar Estudiante
                $gender = strtolower(trim($baseRow['genero']));
                $genderEnum = in_array($gender, ['m', 'masculino']) ? 'masculino' : 'femenino';

                $student = Student::where('user_id', $user->id)->first();

                $studentData = [
                    'career_id' => $careerId,
                    'study_plan_id' => $studyPlanId,
                    'code' => trim($dni),
                    'current_semester' => $semester,
                    'academic_status' => 'regular',
                    'gender' => $genderEnum,
                ];

                if (!$student) {
                    $studentData['user_id'] = $user->id;
                    $studentData['admission_date'] = now();
                    $student = Student::create($studentData);
                } else {
                    $student->update($studentData);
                }

                // 4. Crear Matrícula General (Enrollment) para el Periodo
                $enrollment = Enrollment::where('student_id', $student->id)
                    ->where('academic_period_id', $periodId)
                    ->first();

                if (!$enrollment) {
                    $enrollment = Enrollment::create([
                        'student_id' => $student->id,
                        'academic_period_id' => $periodId,
                        'semester_enrolled' => $semester,
                        'status' => 'active',
                        'enrollment_date' => now(),
                    ]);
                }

                // 5. Procesar las inscripciones a cursos (Registrations)
                // Aquí iteramos sobre CADA fila que tenga este estudiante en el Excel
                foreach ($studentRows as $row) {
                    $shiftName = mb_strtoupper(trim($row['turno']), 'UTF-8');
                    $shiftId = $this->shiftsCache[$shiftName] ?? null;

                    if (!$shiftId) {
                        throw new \Exception("El turno '{$shiftName}' especificado en el DNI {$dni} no existe en el sistema.");
                    }

                    $section = mb_strtoupper(trim($row['seccion']), 'UTF-8');

                    // Buscar asignaciones de docentes para esta fila específica
                    $assignments = TeacherAssignment::where('academic_period_id', $periodId)
                        ->where('shift_id', $shiftId)
                        ->where('section', $section)
                        ->whereHas('didacticUnit', function ($query) use ($studyPlanId, $semester) {
                            $query->where('semester', $semester)
                                ->whereHas('module', function ($q) use ($studyPlanId) {
                                    $q->where('study_plan_id', $studyPlanId);
                                });
                        })
                        ->active()
                        ->get();

                    foreach ($assignments as $assignment) {
                        $registration = Registration::where('enrollment_id', $enrollment->id)
                            ->where('teacher_assignment_id', $assignment->id)
                            ->first();

                        if (!$registration) {
                            Registration::create([
                                'enrollment_id' => $enrollment->id,
                                'teacher_assignment_id' => $assignment->id,
                            ]);

                            $assignment->increment('current_enrolled');
                        }
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e; // Re-lanzar para que Livewire lo capture y muestre el SweetAlert
        }
    }

    public function rules(): array
    {
        return [
            'dni' => 'required|numeric|digits_between:8,12',
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'genero' => 'required|string',
            'cod_carrera' => 'required|exists:careers,code',
            'cod_plan' => 'required|exists:study_plans,code',
            'semestre' => 'required|integer|min:1|max:10',
            'periodo' => 'required|exists:academic_periods,code',
            'seccion' => 'required|string|max:5',
            'turno' => 'required|string|max:50',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'cod_carrera.exists' => 'El código de carrera no existe en el sistema.',
            'cod_plan.exists' => 'El código del plan de estudios no existe.',
            'periodo.exists' => 'El periodo académico especificado no es válido.',
            'turno.required' => 'El turno es obligatorio.',
            'seccion.required' => 'La sección es obligatoria.',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
