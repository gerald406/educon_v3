<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Wellness\SurveyForm;

class EnsureWellnessProfileCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Permitir el paso a invitados
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // 2. Aplicar la restricción SOLO a Estudiantes usando Spatie
        if (!$user->hasRole('Estudiante')) {
            return $next($request);
        }

        // 3. Excepciones de Rutas para evitar bucles infinitos
        $allowedRoutes = [
            'student.onboarding',
            'logout',
            'login',
            'livewire.update',
            'livewire.upload-file',
            'livewire.preview-file',
            'profile.show'
        ];

        if ($request->routeIs($allowedRoutes)) {
            return $next($request);
        }

        // 4. Verificación A: ¿El estudiante tiene sus datos demográficos completos?
        $student = $user->student;

        // Validamos que exista y que los campos demográficos clave NO estén vacíos
        if (
            !$student ||
            empty($student->phone) ||
            empty($student->address) ||
            empty($student->ubigeo_birth_id) ||
            empty($student->origin_school_id)
        ) {

            return redirect()->route('student.onboarding');
        }

        // 5. Verificación B: ¿Existe la Ficha Socioeconómica obligatoria y ya fue respondida?
        $mandatoryForm = SurveyForm::active()->mandatory()->first();

        if ($mandatoryForm) {
            $hasResponded = $student->surveyResponses()
                ->where('survey_form_id', $mandatoryForm->id)
                ->whereIn('status', ['submitted', 'archived'])
                ->exists();

            if (!$hasResponded) {
                return redirect()->route('student.onboarding');
            }
        }

        return $next($request);
    }
}
