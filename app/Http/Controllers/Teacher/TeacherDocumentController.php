<?php

namespace App\Http\Controllers\Teacher;

use App\Exports\TeacherPersonalScheduleExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Services\TeacherPersonalScheduleService;
use Illuminate\Support\Facades\Auth;
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
}
