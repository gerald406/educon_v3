<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Syllabus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class SyllabusPdfController extends Controller
{
    public function download(Syllabus $syllabus)
    {
        $user = Auth::user();

        // 1. Seguridad: Verificar roles y permisos
        // Si NO es Administrador ni Coordinador, validamos que sea el docente dueño
        if (!$user->hasRole(['Administrador', 'Coordinador'])) {
            // Verificamos primero si el usuario tiene un perfil de docente válido
            if (!$user->teacher || $syllabus->teacherAssignment->teacher_id !== $user->teacher->id) {
                abort(403, 'No tienes permiso para ver este sílabo.');
            }
        }

        // 2. Cargar todas las relaciones necesarias para que el PDF no falle
        $syllabus->load([
            'teacherAssignment.didacticUnit.module.studyPlan.career',
            'teacherAssignment.academicPeriod',
            'teacherAssignment.teacher.user',
            'teacherAssignment.shift',
            'indicators.units' // Cargar indicadores y sus sesiones
        ]);

        // 3. Generar el PDF usando la vista
        // Asegúrate de tener instalado dompdf: composer require barryvdh/laravel-dompdf
        $pdf = Pdf::loadView('reports.syllabus-pdf', compact('syllabus'));

        // Configuración opcional del papel
        $pdf->setPaper('A4', 'portrait');

        // 4. Mostrar en el navegador (stream)
        return $pdf->stream('Silabo-' . $syllabus->teacherAssignment->didacticUnit->name . '.pdf');
    }
}