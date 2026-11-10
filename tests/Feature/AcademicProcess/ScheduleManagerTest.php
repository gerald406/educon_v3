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
 * Gestión de horarios (academic-process/schedules) y carga académica.
 */
class ScheduleManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicPeriod $period;

    private Career $career;

    private Module $module;

    private Shift $shift;

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
        $this->module = Module::factory()->create([
            'study_plan_id' => StudyPlan::factory()->create(['career_id' => $this->career->id])->id,
        ]);
        $this->shift = Shift::create(['name' => 'Mañana', 'start_time' => '08:00', 'end_time' => '13:00']);
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
            'shift_id' => $this->shift->id,
            'section' => 'A',
            'status' => 'active',
        ], $attributes));
    }

    /** Componente con curso y sección ya elegidos. */
    private function managerFor(TeacherAssignment $assignment)
    {
        return Livewire::actingAs($this->admin)
            ->test(ScheduleManager::class)
            ->set('selectedCareerId', $this->career->id)
            ->call('selectUnit', $assignment->didactic_unit_id)
            ->set('selectedAssignmentId', $assignment->id);
    }

    private function addBlock($component, string $day, string $start, string $end, $classroomId = '')
    {
        return $component
            ->set('day_of_week', $day)
            ->set('start_time', $start)
            ->set('end_time', $end)
            ->set('classroom_resource_id', $classroomId)
            ->call('addSchedule');
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

    public function test_grid_renders_existing_blocks(): void
    {
        $assignment = $this->section($this->unit());
        Schedule::create(['teacher_assignment_id' => $assignment->id, 'day_of_week' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00']);

        $this->managerFor($assignment)
            ->assertSee('08:00 - 10:00')
            ->assertSee('Horas programadas');
    }

    // ------------------------------------------------------------------
    // Selección (causa de que los bloques "se sobrescriban" en otra sección)
    // ------------------------------------------------------------------

    public function test_selecting_another_course_clears_previous_section(): void
    {
        $first = $this->section($this->unit(['name' => 'Curso Uno']));
        $second = $this->unit(['name' => 'Curso Dos']);
        $this->section($second, ['section' => 'A']);
        $this->section($second, ['section' => 'B']);

        $component = $this->managerFor($first)
            ->assertSet('selectedAssignmentId', $first->id)
            ->call('selectUnit', $second->id)
            ->assertSet('selectedAssignmentId', '')
            ->assertSet('selectedAssignment', null);

        $this->addBlock($component, 'monday', '08:00', '10:00');

        $this->assertSame(0, Schedule::count());
    }

    public function test_typing_a_new_search_clears_the_selection(): void
    {
        $assignment = $this->section($this->unit());

        $this->managerFor($assignment)
            ->set('searchUnit', 'otro curso')
            ->assertSet('selectedUnitId', '')
            ->assertSet('selectedAssignmentId', '')
            ->assertSet('selectedAssignment', null);
    }

    public function test_section_from_another_course_cannot_be_used(): void
    {
        $assignment = $this->section($this->unit());
        $foreign = $this->section($this->unit(), ['section' => 'B']);

        $component = $this->managerFor($assignment)
            ->set('selectedAssignmentId', $foreign->id)
            ->assertSet('selectedAssignment', null);

        $this->addBlock($component, 'monday', '08:00', '10:00');

        $this->assertSame(0, Schedule::count());
    }

    // ------------------------------------------------------------------
    // Cruces
    // ------------------------------------------------------------------

    public function test_overlapping_block_in_same_section_is_rejected(): void
    {
        $component = $this->managerFor($this->section($this->unit()));

        $this->addBlock($component, 'monday', '08:00', '10:00');
        $this->addBlock($component, 'monday', '09:00', '11:00');
        $this->addBlock($component, 'monday', '10:00', '12:00'); // contiguo: válido

        $this->assertSame(2, Schedule::count());
    }

    public function test_teacher_cannot_be_in_two_sections_at_the_same_time(): void
    {
        $teacher = Teacher::factory()->create();
        $a = $this->section($this->unit(), ['teacher_id' => $teacher->id]);
        $b = $this->section($this->unit(['semester' => 3]), ['teacher_id' => $teacher->id]);

        $this->addBlock($this->managerFor($a), 'tuesday', '08:00', '10:00');
        $this->addBlock($this->managerFor($b), 'tuesday', '09:00', '11:00');

        $this->assertSame(1, Schedule::count());
    }

    public function test_same_student_group_cannot_have_two_courses_at_the_same_time(): void
    {
        $courseA = $this->section($this->unit());
        $courseB = $this->section($this->unit());                    // mismo semestre, turno y sección
        $courseC = $this->section($this->unit(), ['section' => 'B']); // otra sección: válido

        $this->addBlock($this->managerFor($courseA), 'wednesday', '08:00', '10:00');
        $this->addBlock($this->managerFor($courseB), 'wednesday', '08:00', '10:00');
        $this->addBlock($this->managerFor($courseC), 'wednesday', '08:00', '10:00');

        $this->assertSame(0, Schedule::where('teacher_assignment_id', $courseB->id)->count());
        $this->assertSame(1, Schedule::where('teacher_assignment_id', $courseC->id)->count());
    }

    public function test_classroom_conflict_ignores_other_periods(): void
    {
        $room = ClassroomResource::factory()->create();
        $oldPeriod = AcademicPeriod::factory()->create(['status' => 'closed', 'code' => 'OLD-I']);
        $old = $this->section($this->unit(), ['academic_period_id' => $oldPeriod->id]);
        Schedule::create(['teacher_assignment_id' => $old->id, 'classroom_resource_id' => $room->id, 'day_of_week' => 'thursday', 'start_time' => '08:00', 'end_time' => '10:00']);

        $current = $this->section($this->unit(['semester' => 2]));
        $other = $this->section($this->unit(['semester' => 4]));

        $this->addBlock($this->managerFor($current), 'thursday', '08:00', '10:00', $room->id); // periodo pasado: válido
        $this->addBlock($this->managerFor($other), 'thursday', '09:00', '11:00', $room->id);   // mismo periodo: cruce

        $this->assertSame(1, Schedule::where('teacher_assignment_id', $current->id)->count());
        $this->assertSame(0, Schedule::where('teacher_assignment_id', $other->id)->count());
    }

    // ------------------------------------------------------------------
    // Edición y eliminación
    // ------------------------------------------------------------------

    public function test_block_can_be_edited_without_conflicting_with_itself(): void
    {
        $assignment = $this->section($this->unit());
        $schedule = Schedule::create(['teacher_assignment_id' => $assignment->id, 'day_of_week' => 'friday', 'start_time' => '08:00', 'end_time' => '10:00']);

        $this->managerFor($assignment)
            ->call('editSchedule', $schedule->id)
            ->assertSet('start_time', '08:00')
            ->set('end_time', '11:00')
            ->call('addSchedule')
            ->assertSet('editingScheduleId', null);

        $this->assertSame(1, Schedule::count());
        $this->assertSame('11:00', $schedule->fresh()->end_time->format('H:i'));
    }

    public function test_only_blocks_of_the_selected_section_can_be_deleted(): void
    {
        $assignment = $this->section($this->unit());
        $foreign = Schedule::create(['teacher_assignment_id' => $this->section($this->unit())->id, 'day_of_week' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00']);

        $this->managerFor($assignment)->call('deleteSchedule', $foreign->id);

        $this->assertNotNull($foreign->fresh());
    }

    public function test_user_without_permission_cannot_add_blocks(): void
    {
        $assignment = $this->section($this->unit());
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ScheduleManager::class)
            ->set('selectedCareerId', $this->career->id)
            ->call('selectUnit', $assignment->didactic_unit_id)
            ->set('day_of_week', 'monday')
            ->set('start_time', '08:00')
            ->set('end_time', '10:00')
            ->call('addSchedule')
            ->assertForbidden();

        $this->assertSame(0, Schedule::count());
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
