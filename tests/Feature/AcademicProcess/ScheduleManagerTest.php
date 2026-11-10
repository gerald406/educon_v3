<?php

namespace Tests\Feature\AcademicProcess;

use App\Livewire\Pages\AcademicProcess\Schedules\ScheduleManager;
use App\Livewire\Pages\AcademicProcess\TeacherAssignments\TeacherAssignmentManager;
use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\ClassroomResource;
use App\Models\DidacticUnit;
use App\Models\Institution;
use App\Models\Module;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\StudyPlan;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gestión de horarios (academic-process/schedules): flujo programa → docente → grilla → UD.
 */
class ScheduleManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicPeriod $period;

    private Career $career;

    private Module $module;

    private Shift $shift;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Docente']); // TeacherFactory asigna el rol
        Permission::firstOrCreate(['name' => 'gestionar-horarios']);
        Permission::firstOrCreate(['name' => 'gestionar-carga-academica']);

        Institution::factory()->create(['status' => 'active']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['gestionar-horarios', 'gestionar-carga-academica']);

        $this->period = AcademicPeriod::factory()->create(['status' => 'active']);
        $this->career = Career::factory()->create();
        $this->module = $this->moduleOf($this->career);
        $this->shift = Shift::create(['name' => 'Mañana', 'start_time' => '08:00', 'end_time' => '13:00']);
        $this->teacher = Teacher::factory()->create();
    }

    private function moduleOf(Career $career): Module
    {
        return Module::factory()->create([
            'study_plan_id' => StudyPlan::factory()->create(['career_id' => $career->id])->id,
        ]);
    }

    private function unit(array $attributes = []): DidacticUnit
    {
        return DidacticUnit::factory()->create(array_merge([
            'module_id' => $this->module->id,
            'semester' => 1,
            'weekly_hours' => 4,
            'status' => 'active',
        ], $attributes));
    }

    private function section(DidacticUnit $unit, array $attributes = []): TeacherAssignment
    {
        return TeacherAssignment::factory()->create(array_merge([
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => $unit->id,
            'teacher_id' => $this->teacher->id,
            'shift_id' => $this->shift->id,
            'section' => 'A',
            'status' => 'active',
        ], $attributes));
    }

    private function block(TeacherAssignment $a, string $day, string $start, string $end, ?int $roomId = null): Schedule
    {
        return Schedule::create([
            'teacher_assignment_id' => $a->id,
            'classroom_resource_id' => $roomId,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /** Componente con programa y docente ya elegidos. */
    private function managerForTeacher(?Teacher $teacher = null, ?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->admin)
            ->test(ScheduleManager::class)
            ->set('selectedCareerId', $this->career->id)
            ->set('selectedTeacherId', ($teacher ?? $this->teacher)->id);
    }

    private function fillBlock($component, string $day, string $start, string $end, $classroomId = '')
    {
        return $component
            ->set('day_of_week', $day)
            ->set('start_time', $start)
            ->set('end_time', $end)
            ->set('classroom_resource_id', $classroomId);
    }

    // ------------------------------------------------------------------
    // Horas como Carbon (antes 'string' → ->format() fallaba)
    // ------------------------------------------------------------------

    public function test_schedule_times_are_carbon_and_stored_normalized(): void
    {
        $schedule = Schedule::create([
            'teacher_assignment_id' => $this->section($this->unit())->id,
            'day_of_week' => 'monday',
            'start_time' => '8:00',
            'end_time' => '10:30',
        ]);

        $fresh = $schedule->fresh();
        $this->assertInstanceOf(Carbon::class, $fresh->start_time);
        $this->assertSame('08:00', $fresh->start_time->format('H:i'));
        $this->assertSame(150, (int) $fresh->start_time->diffInMinutes($fresh->end_time));
        $this->assertSame('08:00:00', $fresh->getRawOriginal('start_time'));
    }

    // ------------------------------------------------------------------
    // Paso 1-2: programa → docentes con carga en el programa
    // ------------------------------------------------------------------

    public function test_teachers_are_listed_only_with_active_load_in_program(): void
    {
        $this->section($this->unit());

        $otherProgram = Teacher::factory()->create();
        TeacherAssignment::factory()->create([
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => DidacticUnit::factory()->create(['module_id' => $this->moduleOf(Career::factory()->create())->id])->id,
            'teacher_id' => $otherProgram->id, 'shift_id' => $this->shift->id, 'status' => 'active',
        ]);

        $suspended = Teacher::factory()->create();
        $this->section($this->unit(), ['teacher_id' => $suspended->id, 'status' => 'suspended']);

        $onlyPlanned = Teacher::factory()->create();
        $planned = AcademicPeriod::factory()->create(['status' => 'planned', 'code' => 'NEXT-I']);
        $this->section($this->unit(), ['teacher_id' => $onlyPlanned->id, 'academic_period_id' => $planned->id]);

        $component = Livewire::actingAs($this->admin)->test(ScheduleManager::class)
            ->set('selectedCareerId', $this->career->id);

        $this->assertSame([$this->teacher->id], $component->instance()->programTeachers->pluck('id')->all());
    }

    public function test_changing_program_clears_teacher_assignment_and_form(): void
    {
        $a = $this->section($this->unit());
        $b = $this->block($a, 'monday', '08:00', '10:00');

        $this->managerForTeacher()
            ->call('editSchedule', $b->id)
            ->assertSet('selectedAssignmentId', $a->id)
            ->set('selectedCareerId', Career::factory()->create()->id)
            ->assertSet('selectedTeacherId', '')
            ->assertSet('selectedAssignmentId', '')
            ->assertSet('editingScheduleId', null)
            ->assertSet('start_time', '');
    }

    public function test_changing_teacher_clears_assignment_and_edit(): void
    {
        $a = $this->section($this->unit());
        $b = $this->block($a, 'monday', '08:00', '10:00');
        $other = Teacher::factory()->create();
        $this->section($this->unit(['semester' => 2]), ['teacher_id' => $other->id]);

        $component = $this->managerForTeacher()
            ->call('editSchedule', $b->id)
            ->set('selectedTeacherId', $other->id)
            ->assertSet('selectedAssignmentId', '')
            ->assertSet('editingScheduleId', null);

        $this->fillBlock($component, 'monday', '10:00', '12:00')->call('saveSchedule');

        $this->assertSame('08:00', $b->fresh()->start_time->format('H:i'));
        $this->assertSame(1, Schedule::count());
    }

    // ------------------------------------------------------------------
    // Paso 3: grilla completa del docente
    // ------------------------------------------------------------------

    public function test_grid_shows_blocks_of_other_programs_read_only(): void
    {
        $mine = $this->section($this->unit());
        $this->block($mine, 'monday', '08:00', '10:00');

        $otherCareer = Career::factory()->create(['name' => 'Enfermería Técnica']);
        $foreign = TeacherAssignment::factory()->create([
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => DidacticUnit::factory()->create(['module_id' => $this->moduleOf($otherCareer)->id])->id,
            'teacher_id' => $this->teacher->id, 'shift_id' => $this->shift->id, 'section' => 'A', 'status' => 'active',
        ]);
        $this->block($foreign, 'monday', '10:00', '12:00');

        $monday = collect($this->managerForTeacher()->instance()->teacherGrid['blocks']['monday']);

        $this->assertTrue($monday->firstWhere('assignment_id', $mine->id)['editable']);
        $foreignBlock = $monday->firstWhere('assignment_id', $foreign->id);
        $this->assertFalse($foreignBlock['editable']);
        $this->assertSame('Enfermería Técnica', $foreignBlock['program']);
    }

    public function test_grid_range_covers_teacher_shifts(): void
    {
        $night = Shift::create(['name' => 'Nocturno', 'start_time' => '18:30', 'end_time' => '22:30']);
        $this->section($this->unit());
        $this->section($this->unit(['semester' => 3]), ['shift_id' => $night->id]);

        $grid = $this->managerForTeacher()->instance()->teacherGrid;
        $this->assertSame('08:00', $grid['from']);
        $this->assertSame('22:30', $grid['to']);

        // Un bloque existente fuera del rango amplía la grilla
        $this->block($this->section($this->unit(['semester' => 5])), 'friday', '07:00', '08:00');
        $this->assertSame('07:00', $this->managerForTeacher()->instance()->teacherGrid['from']);
    }

    // ------------------------------------------------------------------
    // Paso 4: UD del docente en el programa y formulario
    // ------------------------------------------------------------------

    public function test_assignment_list_only_has_program_units(): void
    {
        $mine = $this->section($this->unit());
        TeacherAssignment::factory()->create([
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => DidacticUnit::factory()->create(['module_id' => $this->moduleOf(Career::factory()->create())->id])->id,
            'teacher_id' => $this->teacher->id, 'shift_id' => $this->shift->id, 'status' => 'active',
        ]);

        $ids = $this->managerForTeacher()->instance()->teacherAssignments->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_selecting_assignment_of_other_teacher_is_ignored(): void
    {
        $this->section($this->unit());
        $foreign = $this->section($this->unit(['semester' => 2]), ['teacher_id' => Teacher::factory()->create()->id]);

        $component = $this->managerForTeacher()
            ->call('selectAssignment', $foreign->id)
            ->assertSet('selectedAssignmentId', '');

        $this->fillBlock($component, 'monday', '08:00', '10:00')->call('saveSchedule');

        $this->assertSame(0, Schedule::count());
    }

    public function test_cannot_edit_or_delete_block_of_another_section(): void
    {
        $this->section($this->unit());
        $foreign = $this->section($this->unit(['semester' => 2]), ['teacher_id' => Teacher::factory()->create()->id]);
        $foreignBlock = $this->block($foreign, 'monday', '08:00', '10:00');

        $this->managerForTeacher()
            ->call('editSchedule', $foreignBlock->id)
            ->assertSet('editingScheduleId', null)
            ->call('deleteSchedule', $foreignBlock->id);

        $this->assertNotNull($foreignBlock->fresh());
    }

    public function test_save_creates_block_and_shows_remaining_hours(): void
    {
        $a = $this->section($this->unit());

        $component = $this->managerForTeacher()->call('selectAssignment', $a->id);
        $this->fillBlock($component, 'monday', '08:00', '10:00')
            ->call('saveSchedule')
            ->assertDispatched('swal', fn ($e, $p) => ($p[0]['icon'] ?? null) === 'success')
            ->assertSee('Quedan 2 h');

        $this->assertSame(1, Schedule::where('teacher_assignment_id', $a->id)->count());
    }

    public function test_save_rejects_block_outside_shift(): void
    {
        $a = $this->section($this->unit());

        $component = $this->managerForTeacher()->call('selectAssignment', $a->id);
        $this->fillBlock($component, 'monday', '14:00', '16:00')
            ->call('saveSchedule')
            ->assertDispatched('swal', fn ($e, $p) => ($p[0]['text'] ?? null) === 'El turno Mañana es de 08:00 a 13:00.');

        $this->assertSame(0, Schedule::count());
    }

    public function test_editing_out_of_shift_block_only_classroom_is_rejected(): void
    {
        $a = $this->section($this->unit());
        $b = $this->block($a, 'monday', '19:00', '20:00');
        $room = ClassroomResource::factory()->create();

        $this->managerForTeacher()
            ->call('editSchedule', $b->id)
            ->set('classroom_resource_id', $room->id)
            ->call('saveSchedule')
            ->assertDispatched('swal', fn ($e, $p) => ($p[0]['text'] ?? null) === 'El turno Mañana es de 08:00 a 13:00.');

        $this->assertNull($b->fresh()->classroom_resource_id);
    }

    public function test_use_shift_hours_fills_form(): void
    {
        $a = $this->section($this->unit());

        $this->managerForTeacher()
            ->call('selectAssignment', $a->id)
            ->call('useShiftHours')
            ->assertSet('start_time', '08:00')
            ->assertSet('end_time', '13:00');
    }

    public function test_delete_asks_confirmation_then_deletes(): void
    {
        $a = $this->section($this->unit());
        $b = $this->block($a, 'monday', '08:00', '10:00');

        $component = $this->managerForTeacher()
            ->call('confirmDeleteSchedule', $b->id)
            ->assertDispatched('swal:confirm', fn ($e, $p) => ($p[0]['onConfirmed'] ?? null) === 'deleteSchedule' && ($p[0]['id'] ?? null) === $b->id);

        $this->assertNotNull($b->fresh());

        $component->dispatch('deleteSchedule', id: $b->id);

        $this->assertNull($b->fresh());
    }

    public function test_user_without_permission_gets_403(): void
    {
        $a = $this->section($this->unit());
        $b = $this->block($a, 'monday', '08:00', '10:00');
        $user = User::factory()->create();

        $this->managerForTeacher(null, $user)
            ->call('selectAssignment', $a->id)
            ->call('saveSchedule')
            ->assertForbidden();

        $this->managerForTeacher(null, $user)
            ->call('deleteSchedule', $b->id)
            ->assertForbidden();

        $this->assertNotNull($b->fresh());
    }

    // ------------------------------------------------------------------
    // Vista consolidada (programa + semestre + turno)
    // ------------------------------------------------------------------

    private function consolidated(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->admin)
            ->test(ScheduleManager::class)
            ->call('setViewMode', 'consolidated')
            ->set('selectedCareerId', $this->career->id)
            ->set('filterCycleId', 1)
            ->set('filterShiftId', $this->shift->id);
    }

    public function test_consolidated_ignores_inactive_assignments(): void
    {
        $active = $this->block($this->section($this->unit()), 'monday', '08:00', '10:00');
        $suspended = $this->block($this->section($this->unit(), ['section' => 'B', 'status' => 'suspended']), 'monday', '10:00', '12:00');

        $ids = $this->consolidated()->instance()->consolidatedSchedule->flatten()->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($suspended->id, $ids);
    }

    public function test_progress_never_exceeds_100_with_two_sections(): void
    {
        $unit = $this->unit();
        $this->block($this->section($unit), 'monday', '08:00', '10:00');
        $this->block($this->section($unit, ['section' => 'B']), 'tuesday', '08:00', '10:00');

        $stats = $this->consolidated()->instance()->progressStats;

        $this->assertSame(1, $stats['total_courses']);
        $this->assertSame(1, $stats['scheduled_courses']);
        $this->assertSame(100, (int) $stats['percentage']);
    }

    public function test_consolidated_without_active_period_renders(): void
    {
        $this->period->update(['status' => 'planned']);

        $this->consolidated()->assertOk()->assertSee('Vista Consolidada Semestral');
    }

    public function test_reset_semester_asks_confirmation_and_deletes_only_active(): void
    {
        $a = $this->block($this->section($this->unit()), 'monday', '08:00', '10:00');
        $b = $this->block($this->section($this->unit()), 'tuesday', '08:00', '10:00');
        $kept = $this->block($this->section($this->unit(), ['section' => 'B', 'status' => 'suspended']), 'monday', '10:00', '12:00');

        $component = $this->consolidated()
            ->call('confirmResetSemesterSchedules')
            ->assertDispatched('swal:confirm', fn ($e, $p) => str_contains($p[0]['text'] ?? '', '2 bloques')
                && ($p[0]['onConfirmed'] ?? null) === 'resetSemesterSchedules');

        $this->assertSame(3, Schedule::count());

        $component->dispatch('resetSemesterSchedules');

        $this->assertNull($a->fresh());
        $this->assertNull($b->fresh());
        $this->assertNotNull($kept->fresh());
    }

    // ------------------------------------------------------------------
    // Carga académica: el buscador no debe conservar el curso/docente anterior
    // ------------------------------------------------------------------

    public function test_editing_an_assignment_and_typing_another_teacher_does_not_overwrite_it(): void
    {
        $assignment = $this->section($this->unit());
        $originalTeacher = $assignment->teacher_id;

        Livewire::actingAs($this->admin)
            ->test(TeacherAssignmentManager::class)
            ->call('edit', $assignment->id)
            // 1 carácter: limpia la selección sin ejecutar la búsqueda (usa CONCAT, solo MySQL)
            ->set('searchTeacher', 'x')
            ->call('save')
            ->assertHasErrors(['teacher_id']);

        $this->assertSame($originalTeacher, $assignment->fresh()->teacher_id);
    }
}
