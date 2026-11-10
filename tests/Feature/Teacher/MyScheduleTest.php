<?php

namespace Tests\Feature\Teacher;

use App\Exports\TeacherPersonalScheduleExport;
use App\Livewire\Pages\Teacher\MySchedule;
use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\DidacticUnit;
use App\Models\Institution;
use App\Models\Module;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\StudyPlan;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\TeacherPersonalScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Horario personal del docente: datos armados desde la carga académica y los horarios,
 * página "Mi Horario" y Excel sobre la plantilla oficial.
 */
class MyScheduleTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;

    private Career $career;

    private Module $module;

    private Shift $morning;

    private Shift $night;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Docente']);
        Role::firstOrCreate(['name' => 'Estudiante']);
        Institution::factory()->create(['status' => 'active']);

        $this->period = AcademicPeriod::factory()->create(['status' => 'active', 'code' => '2026-I']);
        $this->career = Career::factory()->create(['name' => 'Electricidad Industrial', 'short_name' => 'Electricidad Ind.']);
        $this->module = Module::factory()->create([
            'study_plan_id' => StudyPlan::factory()->create(['career_id' => $this->career->id])->id,
        ]);
        $this->morning = Shift::create(['name' => 'Diurno', 'start_time' => '08:00', 'end_time' => '13:00']);
        $this->night = Shift::create(['name' => 'Nocturno', 'start_time' => '17:00', 'end_time' => '21:00']);

        $this->teacher = Teacher::factory()->create();
        $this->teacher->user->update(['name' => 'Jorge Antonio', 'lastname' => 'Huarcaya Flores']);
    }

    private function unit(array $attributes = []): DidacticUnit
    {
        return DidacticUnit::factory()->create(array_merge([
            'module_id' => $this->module->id,
            'name' => 'Oportunidades de negocio',
            'semester' => 5,
            'weekly_hours' => 3,
            'theory_hours' => 1,
            'practice_hours' => 2,
            'status' => 'active',
        ], $attributes));
    }

    private function section(DidacticUnit $unit, array $attributes = []): TeacherAssignment
    {
        return TeacherAssignment::factory()->create(array_merge([
            'teacher_id' => $this->teacher->id,
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => $unit->id,
            'shift_id' => $this->morning->id,
            'section' => 'A',
            'status' => 'active',
        ], $attributes));
    }

    private function block(TeacherAssignment $a, string $day, string $start, string $end, ?string $type = null): Schedule
    {
        return Schedule::create([
            'teacher_assignment_id' => $a->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'session_type' => $type,
        ]);
    }

    private function build(): array
    {
        return app(TeacherPersonalScheduleService::class)->build($this->teacher->fresh(), $this->period);
    }

    // --- Datos ---

    public function test_builds_rows_grid_and_total_from_assignments_and_schedules(): void
    {
        $a = $this->section($this->unit());
        $this->block($a, 'monday', '08:00', '08:45', 'T');
        $this->block($a, 'monday', '08:45', '10:15', 'P');

        $data = $this->build();

        $this->assertSame('HUARCAYA FLORES, JORGE ANTONIO', $data['teacher_name']);
        $this->assertSame('HORARIO PERSONAL DEL DOCENTE 2026-I', $data['title']);
        $this->assertSame(3, $data['total_hours']);
        $this->assertSame([], $data['warnings']);

        $row = $data['rows'][0];
        $this->assertSame('Electricidad Ind.', $row['program']);
        $this->assertSame('V-DIURNO', $row['semester_shift']);
        $this->assertSame([1, 2, 3], [$row['theory'], $row['practice'], $row['hours']]);

        $this->assertSame(['Oportunidades de negocio (T) - Electricidad Ind.'], $data['grid']['morning'][0]['monday']);
        $this->assertSame(['Oportunidades de negocio (P) - Electricidad Ind.'], $data['grid']['morning'][1]['monday']);
        $this->assertSame(['Oportunidades de negocio (P) - Electricidad Ind.'], $data['grid']['morning'][2]['monday']);
        $this->assertSame([], $data['grid']['morning'][3]['monday']);
        $this->assertSame(['morning'], $data['groups_with_blocks']);
    }

    public function test_one_row_per_section_and_full_program_name_without_short_name(): void
    {
        $this->career->update(['short_name' => null]);
        $unit = $this->unit();
        $this->section($unit, ['section' => 'A']);
        $this->section($unit, ['section' => 'B', 'shift_id' => $this->night->id]);

        $data = $this->build();

        $this->assertCount(2, $data['rows']);
        $this->assertSame('Electricidad Industrial', $data['rows'][0]['program']);
        $this->assertSame(['V-DIURNO', 'V-NOCTURNO'], array_column($data['rows'], 'semester_shift'));
        $this->assertSame(6, $data['total_hours']);
    }

    public function test_night_blocks_go_to_night_slots(): void
    {
        $a = $this->section($this->unit(), ['shift_id' => $this->night->id]);
        $this->block($a, 'friday', '17:40', '19:00');

        $data = $this->build();

        $this->assertSame([], $data['grid']['night'][0]['friday']);
        $this->assertNotEmpty($data['grid']['night'][1]['friday']);
        $this->assertNotEmpty($data['grid']['night'][2]['friday']);
        $this->assertSame('Oportunidades de negocio - Electricidad Ind.', $data['grid']['night'][1]['friday'][0]);
    }

    public function test_warnings_for_incomplete_misaligned_saturday_and_missing_breakdown(): void
    {
        $a = $this->section($this->unit(['theory_hours' => null, 'practice_hours' => null]));
        $this->block($a, 'monday', '08:10', '09:00');   // no calza: ocupa 1ra y 2da
        $this->block($a, 'saturday', '08:00', '08:45'); // no se imprime
        $this->block($a, 'tuesday', '14:00', '15:00');  // fuera de las franjas

        $data = $this->build();
        $warnings = implode("\n", $data['warnings']);

        $this->assertNotEmpty($data['grid']['morning'][0]['monday']);
        $this->assertNotEmpty($data['grid']['morning'][1]['monday']);
        $this->assertStringContainsString('no tiene registradas las horas de teoría y práctica', $warnings);
        $this->assertStringContainsString('no coincide con las franjas oficiales', $warnings);
        $this->assertStringContainsString('fuera de lunes a viernes', $warnings);
        $this->assertStringContainsString('fuera de las franjas oficiales', $warnings);
    }

    public function test_incomplete_schedule_is_reported_but_total_uses_assigned_hours(): void
    {
        $a = $this->section($this->unit());
        $this->block($a, 'monday', '08:00', '08:45');

        $data = $this->build();

        $this->assertSame(3, $data['total_hours']);
        $this->assertStringContainsString('tiene 1 h programadas de 3 h semanales', implode("\n", $data['warnings']));
    }

    public function test_ignores_other_teachers_other_periods_and_inactive_sections(): void
    {
        $other = Teacher::factory()->create();
        $oldPeriod = AcademicPeriod::factory()->create(['status' => 'closed', 'code' => '2025-II']);

        $this->section($this->unit(['name' => 'UD ajena']), ['teacher_id' => $other->id]);
        $this->section($this->unit(['name' => 'UD antigua']), ['academic_period_id' => $oldPeriod->id]);
        $this->section($this->unit(['name' => 'UD suspendida']), ['status' => 'suspended']);
        $this->section($this->unit(['name' => 'UD propia']));

        $this->assertSame(['UD propia'], array_column($this->build()['rows'], 'unit'));
    }

    // --- Página ---

    public function test_teacher_sees_own_schedule_and_download_link(): void
    {
        $a = $this->section($this->unit());
        $this->block($a, 'monday', '08:00', '10:15', 'P');

        Livewire::actingAs($this->teacher->user)
            ->test(MySchedule::class)
            ->assertSee('HUARCAYA FLORES, JORGE ANTONIO')
            ->assertSee('Oportunidades de negocio (P) - Electricidad Ind.')
            ->assertSee('V-DIURNO')
            ->assertSee(route('teacher.my-schedule.personal-excel'));
    }

    public function test_page_does_not_show_other_teachers_units(): void
    {
        $other = Teacher::factory()->create();
        $this->section($this->unit(['name' => 'UD del otro docente']), ['teacher_id' => $other->id]);
        $this->section($this->unit(['name' => 'UD propia']));

        Livewire::actingAs($this->teacher->user)
            ->test(MySchedule::class)
            ->assertSee('UD propia')
            ->assertDontSee('UD del otro docente');
    }

    public function test_teacher_without_assignments_sees_empty_state(): void
    {
        Livewire::actingAs($this->teacher->user)
            ->test(MySchedule::class)
            ->assertSee('Todavía no tiene unidades didácticas asignadas')
            ->assertDontSee('Descargar horario personal');
    }

    public function test_routes_require_teacher_role(): void
    {
        $student = User::factory()->create();
        $student->assignRole('Estudiante');

        $this->actingAs($student)->get(route('teacher.my-schedule'))->assertForbidden();
        $this->actingAs($student)->get(route('teacher.my-schedule.personal-excel'))->assertForbidden();
        $this->get(route('teacher.my-schedule.personal-excel'));
    }

    public function test_download_without_teacher_profile_is_not_found(): void
    {
        Role::firstOrCreate(['name' => 'Administrador']);
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $this->actingAs($admin)->get(route('teacher.my-schedule.personal-excel'))->assertNotFound();
    }

    // --- Excel ---

    public function test_download_returns_xlsx_of_authenticated_teacher(): void
    {
        $a = $this->section($this->unit());
        $this->block($a, 'monday', '08:00', '10:15', 'P');

        $response = $this->actingAs($this->teacher->user)->get(route('teacher.my-schedule.personal-excel'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('horario-personal-huarcaya-flores-jorge-antonio-2026-i.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_excel_fills_official_template_cells(): void
    {
        $a = $this->section($this->unit());
        $this->block($a, 'monday', '08:00', '08:45', 'T');
        $this->block($a, 'friday', '08:45', '10:15', 'P');
        $night = $this->section($this->unit(['name' => 'Innovación tecnológica', 'semester' => 3]), ['shift_id' => $this->night->id, 'section' => 'B']);
        $this->block($night, 'wednesday', '17:00', '19:00');

        $sheet = $this->exportSheet();

        $this->assertSame('HORARIO PERSONAL DEL DOCENTE 2026-I', $sheet->getCell('A1')->getValue());
        $this->assertSame('HUARCAYA FLORES, JORGE ANTONIO', $sheet->getCell('C3')->getValue());
        $this->assertSame("1ra\n08:00 - 08:45", $sheet->getCell('B7')->getValue());

        // Grilla: lunes 1ra (C7), viernes 2da y 3ra (G8, G9), miércoles noche 1ra-3ra (E14-E16)
        $this->assertSame('Oportunidades de negocio (T) - Electricidad Ind.', $sheet->getCell('C7')->getValue());
        $this->assertSame('Oportunidades de negocio (P) - Electricidad Ind.', $sheet->getCell('G8')->getValue());
        $this->assertSame('Oportunidades de negocio (P) - Electricidad Ind.', $sheet->getCell('G9')->getValue());
        $this->assertNull($sheet->getCell('G10')->getValue());
        $this->assertSame('Innovación tecnológica - Electricidad Ind.', $sheet->getCell('E14')->getValue());
        $this->assertSame('Innovación tecnológica - Electricidad Ind.', $sheet->getCell('E16')->getValue());
        $this->assertNull($sheet->getCell('E17')->getValue());

        // Tabla inferior y total
        $this->assertSame('Electricidad Ind.', $sheet->getCell('B22')->getValue());
        $this->assertSame('Innovación tecnológica', $sheet->getCell('D22')->getValue());
        $this->assertSame('III-NOCTURNO', $sheet->getCell('F22')->getValue());
        $this->assertSame('V-DIURNO', $sheet->getCell('F23')->getValue());
        $this->assertSame([1, 2, 3], [$sheet->getCell('G23')->getValue(), $sheet->getCell('H23')->getValue(), $sheet->getCell('I23')->getValue()]);
        $this->assertSame('=SUM(I22:I29)', $sheet->getCell('H3')->getValue());
        $this->assertEquals(6, $sheet->getCell('H3')->getCalculatedValue());

        // Encabezado con logos y pie de firmas de la plantilla
        $this->assertCount(2, $sheet->getHeaderFooter()->getImages());
        $this->assertStringContainsString('Jefe de Unidad Académica', $sheet->getHeaderFooter()->getOddFooter());
    }

    public function test_excel_inserts_rows_when_more_than_eight_sections(): void
    {
        foreach (range(1, 10) as $i) {
            $this->section($this->unit(['name' => sprintf('UD %02d', $i), 'weekly_hours' => 2, 'theory_hours' => 1, 'practice_hours' => 1]));
        }

        $sheet = $this->exportSheet();

        $this->assertSame('UD 01', $sheet->getCell('D22')->getValue());
        $this->assertSame('UD 10', $sheet->getCell('D31')->getValue());
        $this->assertSame('=SUM(I22:I31)', $sheet->getCell('H3')->getValue());
        $this->assertEquals(20, $sheet->getCell('H3')->getCalculatedValue());
        $this->assertArrayHasKey('D31:E31', $sheet->getMergeCells());
    }

    private function exportSheet()
    {
        $path = tempnam(sys_get_temp_dir(), 'test_horario_');
        (new TeacherPersonalScheduleExport($this->build(), $this->teacher->institution))->save($path);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }
}
