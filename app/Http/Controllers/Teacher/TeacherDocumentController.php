<?php

namespace App\Http\Controllers\Teacher;

use App\Exports\TeacherNonTeachingScheduleExport;
use App\Exports\TeacherPersonalScheduleExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Teacher;
use App\Services\NonTeachingScheduleService;
use App\Services\TeacherPersonalScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * [NUEVO] Documentos oficiales del docente autenticado.
 *
 * Las rutas no reciben ningún id: siempre se usa el docente del usuario en sesión y el
 * período académico activo, de modo que nadie puede descargar documentos de otro docente.
 */
class TeacherDocumentController extends Controller
{
    /** Horario personal del docente en Excel (plantilla oficial). */
    public function personalSchedule(TeacherPersonalScheduleService $service)
    {
        $teacher = Auth::user()->teacher;
        abort_unless($teacher, 404, 'Su usuario no tiene una ficha de docente.');

        $period = AcademicPeriod::where('status', 'active')->first();
        abort_unless($period, 404, 'No hay un período académico activo.');

        $data = $service->build($teacher, $period);

        $path = tempnam(sys_get_temp_dir(), 'horario_');
        (new TeacherPersonalScheduleExport($data, $teacher->institution))->save($path);

        $name = sprintf(
            'horario-personal-%s-%s.xlsx',
            Str::slug(trim(($teacher->user->lastname ?? '').' '.($teacher->user->name ?? ''))) ?: 'docente',
            Str::slug($period->code)
        );

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** [NUEVO] Horario no lectivo en Excel (plantilla oficial). Solo con el horario completo. */
    public function nonTeachingSchedule(NonTeachingScheduleService $service)
    {
        [$teacher, $period, $data] = $this->completeNonTeachingData($service);
        if (! $data) {
            return $this->incomplete();
        }

        $path = tempnam(sys_get_temp_dir(), 'no_lectivo_');
        (new TeacherNonTeachingScheduleExport($data, $teacher->institution))->save($path);

        return response()->download($path, $this->fileName('horario-no-lectivo', $teacher, $period, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** [NUEVO] Programación de Actividades Académicas del Docente en PDF. Solo con el horario completo. */
    public function activityProgram(NonTeachingScheduleService $service)
    {
        [$teacher, $period, $data] = $this->completeNonTeachingData($service);
        if (! $data) {
            return $this->incomplete();
        }

        return Pdf::loadView('reports.teacher.activity-program-pdf', [
            'data' => $data,
            'headerImage' => $this->headerImage($teacher),
            'semesterName' => self::semesterName($period->code),
            'dateText' => self::dateText(now()),
        ])->setPaper('a4', 'portrait')
            ->download($this->fileName('programacion-actividades', $teacher, $period, 'pdf'));
    }

    /**
     * Docente autenticado, período activo y datos de los documentos; los datos son null
     * si el horario no lectivo todavía no está completo.
     */
    private function completeNonTeachingData(NonTeachingScheduleService $service): array
    {
        $teacher = Auth::user()->teacher;
        abort_unless($teacher, 404, 'Su usuario no tiene una ficha de docente.');

        $period = AcademicPeriod::where('status', 'active')->first();
        abort_unless($period, 404, 'No hay un período académico activo.');

        $data = $service->documentData($teacher, $period);

        return [$teacher, $period, $data['is_complete'] ? $data : null];
    }

    private function incomplete()
    {
        return redirect()->route('teacher.non-teaching-schedule')
            ->with('document_error', 'Complete su horario no lectivo antes de descargar los documentos: revise los pendientes.');
    }

    private function fileName(string $prefix, Teacher $teacher, AcademicPeriod $period, string $extension): string
    {
        $name = Str::slug(trim(($teacher->user->lastname ?? '').' '.($teacher->user->name ?? ''))) ?: 'docente';

        return sprintf('%s-%s-%s.%s', $prefix, $name, Str::slug($period->code), $extension);
    }

    /** Encabezado institucional para el PDF (data URI): el de Configuración → Institución o el de la plantilla. */
    private function headerImage(Teacher $teacher): ?string
    {
        $relative = $teacher->institution?->schedule_header_url;
        $path = $relative && Storage::disk('public')->exists($relative)
            ? Storage::disk('public')->path($relative)
            : resource_path('templates/encabezado_horarios.jpg');

        if (! is_file($path) || ! ($info = @getimagesize($path))) {
            return null;
        }

        return 'data:'.$info['mime'].';base64,'.base64_encode(file_get_contents($path));
    }

    /** "2026-I" → "PRIMER SEMESTRE"; "2026-II" → "SEGUNDO SEMESTRE". */
    public static function semesterName(string $periodCode): string
    {
        return match (strtoupper(trim(Str::afterLast($periodCode, '-')))) {
            'I', '1' => 'PRIMER SEMESTRE',
            'II', '2' => 'SEGUNDO SEMESTRE',
            default => 'SEMESTRE',
        };
    }

    /** "11 de noviembre del 2026" */
    public static function dateText(\DateTimeInterface $date): string
    {
        $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return sprintf('%d de %s del %d', $date->format('j'), $months[(int) $date->format('n') - 1], $date->format('Y'));
    }
}
