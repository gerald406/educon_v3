<?php

namespace Tests\Feature\AcademicProcess;

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
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Exportaciones de horarios (PDF/Excel): permiso y validación de parámetros.
 */
class ScheduleReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $student;

    private AcademicPeriod $period;

    private Career $career;

    private Shift $shift;

    private Teacher $teacher;

    private TeacherAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Docente']);
        Role::firstOrCreate(['name' => 'Estudiante']);
        Permission::firstOrCreate(['name' => 'gestionar-horarios']);
        Institution::factory()->create(['status' => 'active']);

        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo('gestionar-horarios');
        $this->student = User::factory()->create();
        $this->student->assignRole('Estudiante');

        $this->period = AcademicPeriod::factory()->create(['status' => 'active']);
        $this->career = Career::factory()->create();
        $module = Module::factory()->create([
            'study_plan_id' => StudyPlan::factory()->create(['career_id' => $this->career->id])->id,
        ]);
        $this->shift = Shift::create(['name' => 'Mañana', 'start_time' => '08:00', 'end_time' => '13:00']);
        $this->teacher = Teacher::factory()->create();
        $this->assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => DidacticUnit::factory()->create(['module_id' => $module->id, 'semester' => 1, 'status' => 'active'])->id,
            'teacher_id' => $this->teacher->id,
            'shift_id' => $this->shift->id,
            'section' => 'A',
            'status' => 'active',
        ]);
        Schedule::create(['teacher_assignment_id' => $this->assignment->id, 'day_of_week' => 'tuesday', 'start_time' => '10:00', 'end_time' => '12:00']);
        Schedule::create(['teacher_assignment_id' => $this->assignment->id, 'day_of_week' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00']);
    }

    private function urls(): array
    {
        $consolidated = ['career' => $this->career->id, 'cycle' => 1, 'shift' => $this->shift->id, 'period_id' => $this->period->id];

        return [
            route('academic-process.schedules.export', ['type' => 'teacher', 'format' => 'pdf', 'id' => $this->teacher->id, 'period_id' => $this->period->id]),
            route('academic-process.schedules.export-consolidated', $consolidated),
            route('academic-process.schedules.export-consolidated-pdf', $consolidated),
        ];
    }

    public function test_exports_are_forbidden_without_permission(): void
    {
        foreach ($this->urls() as $url) {
            $this->actingAs($this->student)->get($url)->assertForbidden();
        }
    }

    public function test_exports_work_with_permission(): void
    {
        [$teacherPdf, , $consolidatedPdf] = $this->urls();

        $this->actingAs($this->manager)->get($teacherPdf)->assertOk();
        $this->actingAs($this->manager)->get($consolidatedPdf)->assertOk();
    }

    public function test_consolidated_exports_validate_parameters(): void
    {
        foreach (['export-consolidated', 'export-consolidated-pdf'] as $name) {
            $this->actingAs($this->manager)->get(route("academic-process.schedules.$name"))
                ->assertSessionHasErrors(['career', 'cycle', 'shift', 'period_id']);

            $this->actingAs($this->manager)->get(route("academic-process.schedules.$name", [
                'career' => $this->career->id, 'cycle' => 1, 'shift' => 999, 'period_id' => $this->period->id,
            ]))->assertSessionHasErrors(['shift']);
        }
    }

    public function test_consolidated_pdf_ignores_inactive_assignments(): void
    {
        $suspended = TeacherAssignment::factory()->create([
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => $this->assignment->didactic_unit_id,
            'teacher_id' => $this->teacher->id,
            'shift_id' => $this->shift->id,
            'section' => 'B',
            'status' => 'suspended',
        ]);
        $hidden = Schedule::create(['teacher_assignment_id' => $suspended->id, 'day_of_week' => 'monday', 'start_time' => '10:00', 'end_time' => '12:00']);

        $captured = null;
        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) use (&$captured, $pdf) {
            $captured = $data;

            return $pdf;
        });

        [, , $consolidatedPdf] = $this->urls();
        $this->actingAs($this->manager)->get($consolidatedPdf)->assertOk();

        $ids = collect($captured['schedules'])->flatten()->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_teacher_report_orders_days_without_mysql_field(): void
    {
        $captured = null;
        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('stream')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) use (&$captured, $pdf) {
            $captured = $data;

            return $pdf;
        });

        [$teacherPdf] = $this->urls();
        $this->actingAs($this->manager)->get($teacherPdf)->assertOk();

        $this->assertSame(['monday', 'tuesday'], $captured['schedules']->pluck('day_of_week')->all());
    }
}
