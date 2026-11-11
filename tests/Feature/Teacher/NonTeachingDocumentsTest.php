<?php

namespace Tests\Feature\Teacher;

use App\Exports\TeacherNonTeachingScheduleExport;
use App\Http\Controllers\Teacher\TeacherDocumentController;
use App\Livewire\Pages\Teacher\NonTeachingSchedule;
use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\DidacticUnit;
use App\Models\Institution;
use App\Models\Module;
use App\Models\NonTeachingActivity;
use App\Models\NonTeachingPlan;
use App\Models\Shift;
use App\Models\StudyPlan;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\NonTeachingScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Documentos del horario no lectivo (Excel oficial y PDF de programación de actividades),
 * copia desde el período anterior y bloqueo sin carga lectiva.
 */
class NonTeachingDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;

    private Career $career;

    private Module $module;

    private Shift $night;

    private Teacher $teacher;

    private NonTeachingScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Docente']);
        Role::firstOrCreate(['name' => 'Administrador']);
        Institution::factory()->create(['status' => 'active']);

        $this->period = AcademicPeriod::factory()->create(['status' => 'active', 'code' => '2026-II']);
        $this->career = Career::factory()->create(['name' => 'Electricidad Industrial', 'short_name' => 'Electricidad Ind.']);
        $this->module = Module::factory()->create([
            'study_plan_id' => StudyPlan::factory()->create(['career_id' => $this->career->id])->id,
        ]);
        // Turno noche: las clases no ocupan la grilla no lectiva de 08:00 a 16:00
        $this->night = Shift::create(['name' => 'Nocturno', 'start_time' => '17:00', 'end_time' => '21:00']);

        $this->teacher = Teacher::factory()->create([
            'workday_type' => 'part_time', 'contract_type' => 'permanent', 'career_id' => $this->career->id,
        ]);
        $this->teacher->user->update(['name' => 'Jorge Antonio', 'lastname' => 'Huarcaya Flores']);
        $this->service = app(NonTeachingScheduleService::class);
    }

    private function lective(int $hours, string $name = 'Redes', ?AcademicPeriod $period = null): TeacherAssignment
    {
        $unit = DidacticUnit::factory()->create([
            'module_id' => $this->module->id, 'name' => $name, 'semester' => 3, 'weekly_hours' => $hours, 'status' => 'active',
        ]);

        return TeacherAssignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'academic_period_id' => ($period ?? $this->period)->id,
            'didactic_unit_id' => $unit->id,
            'shift_id' => $this->night->id,
            'section' => 'b',
            'status' => 'active',
        ]);
    }

    /** Tiempo parcial: 12 lectivas + 3 de preparación + 5 de investigación = 20, todo ubicado. */
    private function completePlan(?AcademicPeriod $period = null): NonTeachingPlan
    {
        $period ??= $this->period;
        $this->lective(12, 'Redes', $period);
        $plan = $this->service->ensurePlan($this->teacher->fresh(), $period);
        $preparation = $plan->activities()->where('activity', NonTeachingActivity::PREPARATION)->first();

        $this->assertNull($this->service->saveActivity($plan, $preparation->id, '', 'Elaboración de sesiones de aprendizaje', null));
        $this->assertNull($this->service->saveActivity($plan, null, 'research_innovation', 'Proyecto de innovación tecnológica', 5));
        $research = $plan->activities()->where('activity', 'research_innovation')->first();

        foreach (['08:00', '09:00', '10:00'] as $time) {
            $this->assertNull($this->service->placeBlock($plan, $preparation->id, 'monday', $time));
        }
        foreach (['08:00', '09:00', '10:00', '11:00', '12:00'] as $time) {
            $this->assertNull($this->service->placeBlock($plan, $research->id, 'tuesday', $time));
        }

        return $plan;
    }

    private function data(): array
    {
        return $this->service->documentData($this->teacher->fresh(), $this->period);
    }

    // --- Datos ---

    public function test_document_data_comes_from_what_the_teacher_configured(): void
    {
        $this->completePlan();
        $data = $this->data();

        $this->assertTrue($data['is_complete']);
        $this->assertSame('HUARCAYA FLORES, JORGE ANTONIO', $data['teacher_name']);
        $this->assertSame('Electricidad Industrial', $data['program']);
        $this->assertSame([20, 12, 8, 20], [$data['workday_hours'], $data['lective_hours'], (int) $data['non_teaching_hours'], (int) $data['total_hours']]);
        $this->assertSame(['e', 'd'], array_column($data['activities'], 'letter')); // Preparación primero
        $this->assertSame('Proyecto de innovación tecnológica', $data['activities'][1]['sub_activity']);
        $this->assertSame('Redes', $data['lective_rows'][0]['unit']);
    }

    // --- Excel ---

    public function test_excel_fills_official_non_teaching_template(): void
    {
        $this->completePlan();

        $path = tempnam(sys_get_temp_dir(), 'test_nl_');
        (new TeacherNonTeachingScheduleExport($this->data(), $this->teacher->institution))->save($path);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame('HUARCAYA FLORES, JORGE ANTONIO', $sheet->getCell('C3')->getValue());
        $this->assertSame('Electricidad Industrial', $sheet->getCell('F3')->getValue());
        $this->assertStringContainsString('HORARIO DEL DOCENTE 2026-II', $sheet->getHeaderFooter()->getOddHeader());
        $this->assertStringContainsString('Jefe de Unidad Académica', $sheet->getHeaderFooter()->getOddFooter());
        $this->assertCount(1, $sheet->getHeaderFooter()->getImages());

        // Grilla: lunes 08-11 preparación (C5:C7), martes 08-13 investigación (D5:D9)
        $this->assertSame("1ra\n08:00 - 09:00", $sheet->getCell('B5')->getValue());
        $this->assertSame('Preparación de clases', $sheet->getCell('C5')->getValue());
        $this->assertSame('Preparación de clases', $sheet->getCell('C7')->getValue());
        $this->assertNull($sheet->getCell('C8')->getValue());
        $this->assertSame('Investigación e innovación', $sheet->getCell('D9')->getValue());
        $this->assertNull($sheet->getCell('D10')->getValue());

        // Carga lectiva
        $this->assertSame('Electricidad Ind.', $sheet->getCell('B15')->getValue());
        $this->assertSame('Redes', $sheet->getCell('D15')->getValue());
        $this->assertSame('III-NOCTURNO', $sheet->getCell('F15')->getValue());
        $this->assertEquals(12, $sheet->getCell('G20')->getCalculatedValue());

        // Carga no lectiva: fila 27 = Investigación, fila 28 = Preparación (orden oficial)
        $this->assertStringContainsString('INVESTIGACI', $sheet->getCell('B27')->getValue());
        $this->assertSame('Proyecto de innovación tecnológica', $sheet->getCell('D27')->getValue());
        $this->assertEquals(5, $sheet->getCell('G27')->getValue());
        $this->assertSame('Elaboración de sesiones de aprendizaje', $sheet->getCell('D28')->getValue());
        $this->assertEquals(3, $sheet->getCell('G28')->getValue());
        $this->assertNull($sheet->getCell('D24')->getValue());
        $this->assertEquals(8, $sheet->getCell('G32')->getCalculatedValue());
        $this->assertEquals(20, $sheet->getCell('G33')->getCalculatedValue());
    }

    public function test_excel_inserts_rows_for_more_than_five_lective_sections(): void
    {
        $data = [
            'teacher_name' => 'DOCENTE, PRUEBA', 'program' => 'Programa', 'period_code' => '2026-I', 'blocks' => [],
            'lective_rows' => array_map(fn ($i) => [
                'program' => 'Programa', 'unit' => "UD {$i}", 'semester_shift' => 'I-DIURNO', 'section' => 'A', 'hours' => 2,
            ], range(1, 7)),
            'activities' => [['key' => 'other', 'sub_activity' => 'Comisión', 'hours' => 1.5]],
        ];

        $sheet = (new TeacherNonTeachingScheduleExport($data))->spreadsheet()->getActiveSheet();

        $this->assertSame('UD 7', $sheet->getCell('D21')->getValue());
        $this->assertEquals(14, $sheet->getCell('G22')->getCalculatedValue());   // total lectivo, desplazado 2 filas
        $this->assertStringContainsString('OTRAS', $sheet->getCell('B33')->getValue());
        $this->assertSame('Comisión', $sheet->getCell('D33')->getValue());
        $this->assertEquals(1.5, $sheet->getCell('G34')->getCalculatedValue());  // total no lectivo
        $this->assertEquals(15.5, $sheet->getCell('G35')->getCalculatedValue()); // total general
    }

    // --- Descargas ---

    public function test_downloads_work_only_with_complete_plan(): void
    {
        $this->lective(12);
        $this->service->ensurePlan($this->teacher->fresh(), $this->period);

        foreach (['teacher.non-teaching-schedule.excel', 'teacher.non-teaching-schedule.activity-program-pdf'] as $route) {
            $this->actingAs($this->teacher->user)->get(route($route))
                ->assertRedirect(route('teacher.non-teaching-schedule'))
                ->assertSessionHas('document_error');
        }
    }

    public function test_complete_plan_downloads_excel_and_pdf(): void
    {
        $this->completePlan();

        $excel = $this->actingAs($this->teacher->user)->get(route('teacher.non-teaching-schedule.excel'));
        $excel->assertOk();
        $excel->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('horario-no-lectivo-huarcaya-flores-jorge-antonio-2026-ii.xlsx', $excel->headers->get('content-disposition'));

        $pdf = $this->actingAs($this->teacher->user)->get(route('teacher.non-teaching-schedule.activity-program-pdf'));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
    }

    public function test_downloads_require_teacher_profile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $this->actingAs($admin)->get(route('teacher.non-teaching-schedule.excel'))->assertNotFound();
        $this->actingAs($admin)->get(route('teacher.non-teaching-schedule.activity-program-pdf'))->assertNotFound();
    }

    public function test_pdf_view_shows_official_content(): void
    {
        $this->completePlan();

        $html = view('reports.teacher.activity-program-pdf', [
            'data' => $this->data(),
            'headerImage' => null,
            'semesterName' => TeacherDocumentController::semesterName('2026-II'),
            'dateText' => TeacherDocumentController::dateText(Carbon::create(2026, 11, 11)),
        ])->render();

        $this->assertStringContainsString('SEGUNDO SEMESTRE 2026-II', $html);
        $this->assertStringContainsString('HUARCAYA FLORES, JORGE ANTONIO', $html);
        $this->assertStringContainsString('HORAS SEMANALES:</span> 20', $html);
        $this->assertStringContainsString('NOMBRADO ( X )', $html);
        $this->assertStringContainsString('Electricidad Industrial', $html);
        $this->assertStringContainsString('e) Preparación de clases.', $html);
        $this->assertStringContainsString('d) Investigación e innovación.', $html);
        $this->assertStringContainsString('Proyecto de innovación tecnológica', $html);
        $this->assertStringContainsString('<td class="center">B</td>', $html); // sección en mayúscula
        $this->assertStringContainsString('Puno, 11 de noviembre del 2026', $html);
        $this->assertStringContainsString('Coordinador del Programa de Estudios', $html);
        $this->assertStringNotContainsString('Desarrollo institucional', $html); // solo lo configurado
    }

    public function test_semester_name_from_period_code(): void
    {
        $this->assertSame('PRIMER SEMESTRE', TeacherDocumentController::semesterName('2026-I'));
        $this->assertSame('SEGUNDO SEMESTRE', TeacherDocumentController::semesterName('2026-II'));
        $this->assertSame('SEMESTRE', TeacherDocumentController::semesterName('2026'));
    }

    // --- Sin carga lectiva ---

    public function test_teacher_without_lective_load_cannot_register(): void
    {
        Livewire::actingAs($this->teacher->user)
            ->test(NonTeachingSchedule::class)
            ->assertSee('Todavía no tiene carga lectiva')
            ->call('newActivity')
            ->set('activity', 'other')
            ->set('sub_activity', 'Texto válido')
            ->set('hours', '2')
            ->call('saveActivity');

        $this->assertSame(0, NonTeachingPlan::count());
    }

    // --- Copia del período anterior ---

    public function test_copy_from_previous_period(): void
    {
        $old = AcademicPeriod::factory()->create(['status' => 'closed', 'code' => '2026-I']);
        $this->completePlan($old);
        $this->lective(12, 'Redes II');

        $component = Livewire::actingAs($this->teacher->user)
            ->test(NonTeachingSchedule::class)
            ->assertSee('Copiar del período anterior')
            ->call('copyFromPrevious')
            ->assertDispatched('swal', fn ($e, $p) => ($p[0]['icon'] ?? null) === 'success');

        $plan = NonTeachingPlan::where('academic_period_id', $this->period->id)->firstOrFail();
        $this->assertSame(2, $plan->activities()->count());
        $this->assertSame('Proyecto de innovación tecnológica', $plan->activities()->where('activity', 'research_innovation')->value('sub_activity'));
        $this->assertSame('Elaboración de sesiones de aprendizaje', $plan->activities()->where('activity', NonTeachingActivity::PREPARATION)->value('sub_activity'));
        $this->assertSame(8, $plan->blocks()->count());
        $this->assertTrue($this->data()['is_complete']);

        // Con actividades propias ya no se ofrece ni se repite la copia
        $component->assertDontSee('Copiar del período anterior')
            ->call('copyFromPrevious')
            ->assertDispatched('swal', fn ($e, $p) => ($p[0]['icon'] ?? null) === 'warning');
        $this->assertSame(8, $plan->blocks()->count());
        $this->assertSame(8, NonTeachingPlan::where('academic_period_id', $old->id)->first()->blocks()->count());
    }

    public function test_copy_is_not_offered_without_previous_plan(): void
    {
        $this->lective(12);

        Livewire::actingAs($this->teacher->user)
            ->test(NonTeachingSchedule::class)
            ->assertDontSee('Copiar del período anterior')
            ->call('copyFromPrevious')
            ->assertDispatched('swal', fn ($e, $p) => ($p[0]['icon'] ?? null) === 'warning');
    }
}
