<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Pages\People\Teachers\TeacherManager;
use App\Livewire\Pages\Teacher\NonTeachingSchedule;
use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\DidacticUnit;
use App\Models\Institution;
use App\Models\Module;
use App\Models\NonTeachingActivity;
use App\Models\NonTeachingBlock;
use App\Models\NonTeachingPlan;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\StudyPlan;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\NonTeachingScheduleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Horario no lectivo: Anexo 2, jornada, límite de actividades, subactividad,
 * ubicación en la semana y cruces con el horario lectivo.
 */
class NonTeachingScheduleTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;

    private Module $module;

    private Shift $morning;

    private Teacher $teacher;

    private NonTeachingScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Docente']);
        Role::firstOrCreate(['name' => 'Estudiante']);
        Institution::factory()->create(['status' => 'active']);

        $this->period = AcademicPeriod::factory()->create(['status' => 'active', 'code' => '2026-I']);
        $this->module = Module::factory()->create([
            'study_plan_id' => StudyPlan::factory()->create(['career_id' => Career::factory()->create()->id])->id,
        ]);
        $this->morning = Shift::create(['name' => 'Diurno', 'start_time' => '08:00', 'end_time' => '13:00']);
        $this->teacher = Teacher::factory()->create(['workday_type' => 'full_time']);
        $this->service = app(NonTeachingScheduleService::class);
    }

    /** Asigna al docente una UD con las horas lectivas indicadas. */
    private function lective(int $hours, ?Teacher $teacher = null, string $name = 'Redes'): TeacherAssignment
    {
        $unit = DidacticUnit::factory()->create([
            'module_id' => $this->module->id, 'name' => $name, 'semester' => 1, 'weekly_hours' => $hours, 'status' => 'active',
        ]);

        return TeacherAssignment::factory()->create([
            'teacher_id' => ($teacher ?? $this->teacher)->id,
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => $unit->id,
            'shift_id' => $this->morning->id,
            'section' => 'A',
            'status' => 'active',
        ]);
    }

    private function plan(?Teacher $teacher = null): NonTeachingPlan
    {
        return $this->service->ensurePlan(($teacher ?? $this->teacher)->fresh(), $this->period);
    }

    private function preparation(NonTeachingPlan $plan): NonTeachingActivity
    {
        return $plan->activities()->where('activity', NonTeachingActivity::PREPARATION)->firstOrFail();
    }

    private function summary(): array
    {
        return $this->service->summary($this->teacher->fresh(), $this->period);
    }

    // --- Anexo 2 ---

    public function test_preparation_hours_follow_annex_two(): void
    {
        $expected = [
            4 => 1, 5 => 1.5, 6 => 1.5, 7 => 2, 8 => 2, 9 => 2.5, 10 => 2.5, 11 => 3, 12 => 3, 13 => 3.5,
            14 => 3.5, 15 => 4, 16 => 4, 17 => 4.5, 18 => 4.5, 19 => 5, 20 => 5,
        ];

        foreach ($expected as $lective => $hours) {
            $this->assertSame((int) ($hours * 60), $this->service->preparationMinutes($lective), "lectivas: {$lective}");
        }
    }

    public function test_preparation_keeps_progression_above_twenty_and_is_zero_below_four(): void
    {
        $this->assertSame(330, $this->service->preparationMinutes(21)); // 5.5
        $this->assertSame(330, $this->service->preparationMinutes(22));
        $this->assertSame(390, $this->service->preparationMinutes(26)); // 6.5
        $this->assertSame(0, $this->service->preparationMinutes(3));
        $this->assertSame(0, $this->service->preparationMinutes(0));
    }

    public function test_plan_creates_preparation_and_recalculates_when_load_changes(): void
    {
        $this->lective(10);
        $plan = $this->plan();

        $this->assertSame(150, $this->preparation($plan)->weekly_minutes);
        $this->assertSame(40, $plan->workday_hours);

        $this->lective(6, null, 'Ofimática'); // 16 lectivas → 4 h
        $this->plan();

        $this->assertSame(240, $this->preparation($plan)->weekly_minutes);
        $this->assertSame(1, $plan->activities()->count());
    }

    public function test_only_active_assignments_of_the_period_count(): void
    {
        $this->lective(10);
        $this->lective(6, null, 'Suspendida')->update(['status' => 'suspended']);
        $this->lective(8, Teacher::factory()->create(), 'De otro docente');

        $this->assertSame(10, $this->service->lectiveHours($this->teacher, $this->period));
    }

    // --- Jornada ---

    public function test_full_time_plan_is_complete_only_with_exactly_forty_hours(): void
    {
        $this->lective(20);
        $plan = $this->plan();
        $this->assertNull($this->service->saveActivity($plan, $this->preparation($plan)->id, '', 'Planificación de sesiones', null));
        $this->assertNull($this->service->saveActivity($plan, null, 'research_innovation', 'Proyecto de innovación', 15));

        $summary = $this->summary();
        $this->assertSame(2400, $summary['total_minutes']);
        $this->assertSame(0, $summary['difference_minutes']);
        // Jornada completa, pero faltan ubicar las horas en la semana
        $this->assertFalse($summary['is_complete']);
        $this->assertStringContainsString('faltan ubicar 5 h', implode("\n", $summary['issues']));
    }

    public function test_hours_above_the_workday_are_rejected(): void
    {
        $this->lective(20);
        $plan = $this->plan(); // 20 + 5 de preparación: quedan 15

        $error = $this->service->saveActivity($plan, null, 'research_innovation', 'Proyecto de innovación', 15.5);

        $this->assertStringContainsString('Puede asignar hasta 15 h', $error);
        $this->assertSame(1, $plan->activities()->count());
    }

    public function test_part_time_teacher_has_twenty_hour_workday(): void
    {
        $this->teacher->update(['workday_type' => 'part_time']);
        $this->lective(12); // preparación 3 → quedan 5
        $plan = $this->plan();

        $this->assertSame(20, $plan->workday_hours);
        $this->assertStringContainsString('Puede asignar hasta 5 h', $this->service->saveActivity($plan, null, 'other', 'Comisión de inventario', 6));
        $this->assertNull($this->service->saveActivity($plan, null, 'other', 'Comisión de inventario', 5));
        $this->assertSame(0, $this->summary()['difference_minutes']);
    }

    public function test_lective_load_above_workday_is_reported(): void
    {
        $this->teacher->update(['workday_type' => 'part_time']);
        $this->lective(22);
        $this->plan();

        $this->assertStringContainsString('supera en 7.5 h la jornada de 20 h', implode("\n", $this->summary()['issues']));
    }

    public function test_lective_load_below_four_hours_is_reported(): void
    {
        $this->lective(3);
        $plan = $this->plan();

        $this->assertSame(0, $this->preparation($plan)->weekly_minutes);
        $this->assertStringContainsString('desde 4 horas lectivas', $this->summary()['issues'][0]);
    }

    // --- Actividades y subactividades ---

    public function test_maximum_four_activities_including_preparation(): void
    {
        $this->lective(10);
        $plan = $this->plan();

        foreach (['academic_design', 'institutional_development', 'productive_sector'] as $key) {
            $this->assertNull($this->service->saveActivity($plan, null, $key, 'Subactividad de prueba', 2));
        }

        $this->assertStringContainsString('como máximo 4 actividades', $this->service->saveActivity($plan, null, 'other', 'Una quinta actividad', 1));
        $this->assertSame(4, $plan->activities()->count());
        $this->assertFalse($this->summary()['can_add_activity']);
    }

    public function test_activity_validations(): void
    {
        $this->lective(10);
        $plan = $this->plan();

        $this->assertStringContainsString('5 a 255', $this->service->saveActivity($plan, null, 'other', 'abc', 2));
        $this->assertStringContainsString('5 a 255', $this->service->saveActivity($plan, null, 'other', str_repeat('a', 256), 2));
        $this->assertStringContainsString('Seleccione una actividad', $this->service->saveActivity($plan, null, 'inventada', 'Texto válido', 2));
        $this->assertStringContainsString('Seleccione una actividad', $this->service->saveActivity($plan, null, NonTeachingActivity::PREPARATION, 'Texto válido', 2));
        $this->assertStringContainsString('medias horas', $this->service->saveActivity($plan, null, 'other', 'Texto válido', 0.5));
        $this->assertStringContainsString('medias horas', $this->service->saveActivity($plan, null, 'other', 'Texto válido', 1.25));
        $this->assertStringContainsString('medias horas', $this->service->saveActivity($plan, null, 'other', 'Texto válido', 'dos'));

        $this->assertNull($this->service->saveActivity($plan, null, 'other', 'Texto válido', 1.5));
        $this->assertStringContainsString('ya está registrada', $this->service->saveActivity($plan, null, 'other', 'Otra subactividad', 1));
        $this->assertSame(90, $plan->activities()->where('activity', 'other')->value('weekly_minutes'));
    }

    public function test_preparation_hours_cannot_be_edited_nor_removed(): void
    {
        $this->lective(10);
        $plan = $this->plan();
        $preparation = $this->preparation($plan);

        $this->assertNull($this->service->saveActivity($plan, $preparation->id, 'other', 'Elaboración de sesiones', 20));
        $preparation->refresh();
        $this->assertSame([NonTeachingActivity::PREPARATION, 150, 'Elaboración de sesiones'], [$preparation->activity, $preparation->weekly_minutes, $preparation->sub_activity]);

        $this->assertStringContainsString('obligatoria', $this->service->removeActivity($plan, $preparation->id));
        $this->assertSame(1, $plan->activities()->count());
    }

    // --- Ubicación en la semana ---

    public function test_blocks_are_placed_as_full_hours_and_one_half_hour(): void
    {
        $this->lective(10); // preparación 2.5 h
        $plan = $this->plan();
        $id = $this->preparation($plan)->id;

        $this->assertNull($this->service->placeBlock($plan, $id, 'monday', '13:30')); // hora completa 13:00-14:00
        $this->assertNull($this->service->placeBlock($plan, $id, 'monday', '14:00'));
        $this->assertNull($this->service->placeBlock($plan, $id, 'tuesday', '08:30')); // media hora 08:30-09:00
        $this->assertStringContainsString('ya tiene todas sus horas ubicadas', $this->service->placeBlock($plan, $id, 'friday', '10:00'));

        $blocks = $plan->blocks()->orderBy('day_of_week')->orderBy('start_time')->get()
            ->map(fn (NonTeachingBlock $b) => $b->day_of_week.' '.$b->start_time->format('H:i').'-'.$b->end_time->format('H:i'))->all();
        $this->assertSame(['monday 13:00-14:00', 'monday 14:00-15:00', 'tuesday 08:30-09:00'], $blocks);
        $this->assertSame(150, $this->summary()['activities'][0]['placed_minutes']);
    }

    public function test_block_cannot_share_minutes_with_a_class(): void
    {
        $a = $this->lective(10);
        Schedule::create(['teacher_assignment_id' => $a->id, 'day_of_week' => 'monday', 'start_time' => '08:45', 'end_time' => '09:30']);
        $plan = $this->plan();
        $this->service->saveActivity($plan, null, 'other', 'Comisión de inventario', 2);
        $other = $plan->activities()->where('activity', 'other')->value('id');

        // 08:00-09:00 y 09:00-10:00 comparten minutos con la clase de 08:45 a 09:30
        $this->assertStringContainsString('no está completamente libre', $this->service->placeBlock($plan, $other, 'monday', '08:00'));
        $this->assertStringContainsString('tiene clase de Redes', $this->service->placeBlock($plan, $other, 'monday', '09:00'));
        $this->assertNull($this->service->placeBlock($plan, $other, 'monday', '10:00'));
        $this->assertNull($this->service->placeBlock($plan, $other, 'tuesday', '08:00')); // otro día sí

        // La media hora de Preparación (2.5 h) sí cabe en 08:00-08:30
        $preparation = $this->preparation($plan)->id;
        $this->assertNull($this->service->placeBlock($plan, $preparation, 'wednesday', '08:00'));
        $this->assertNull($this->service->placeBlock($plan, $preparation, 'wednesday', '09:00'));
        $this->assertNull($this->service->placeBlock($plan, $preparation, 'monday', '08:00'));
        $this->assertSame('08:30', $plan->blocks()->where('day_of_week', 'monday')->where('start_time', '08:00:00')->first()->end_time->format('H:i'));
    }

    public function test_block_cannot_overlap_another_non_teaching_block_nor_leave_the_grid(): void
    {
        $this->lective(8); // preparación 2 h
        $plan = $this->plan();
        $this->service->saveActivity($plan, null, 'other', 'Comisión de inventario', 2);
        $other = $plan->activities()->where('activity', 'other')->value('id');
        $preparation = $this->preparation($plan)->id;

        $this->assertNull($this->service->placeBlock($plan, $preparation, 'monday', '13:00'));
        $this->assertStringContainsString('ocupada', $this->service->placeBlock($plan, $other, 'monday', '13:30'));
        $this->assertStringContainsString('entre las 08:00 y las 16:00', $this->service->placeBlock($plan, $other, 'monday', '16:00'));
        $this->assertStringContainsString('entre las 08:00 y las 16:00', $this->service->placeBlock($plan, $other, 'monday', '07:30'));
        $this->assertSame('Casilla no válida.', $this->service->placeBlock($plan, $other, 'saturday', '10:00'));
        $this->assertSame('Casilla no válida.', $this->service->placeBlock($plan, $other, 'monday', '10:15'));
        $this->assertSame(1, $plan->blocks()->count());
    }

    public function test_class_added_later_marks_the_block_as_conflict(): void
    {
        $a = $this->lective(8);
        $plan = $this->plan();
        $this->service->placeBlock($plan, $this->preparation($plan)->id, 'monday', '09:00');

        Schedule::create(['teacher_assignment_id' => $a->id, 'day_of_week' => 'monday', 'start_time' => '09:30', 'end_time' => '10:15']);

        $summary = $this->summary();
        $this->assertSame('Redes', $summary['blocks'][0]['conflict']);
        $this->assertStringContainsString('se cruza con la clase de Redes', implode("\n", $summary['issues']));
        $this->assertSame(1, $plan->blocks()->count()); // no se borra: el docente lo reubica
    }

    public function test_hours_cannot_be_reduced_below_placed_blocks_and_removing_activity_removes_blocks(): void
    {
        $this->lective(8);
        $plan = $this->plan();
        $this->service->saveActivity($plan, null, 'other', 'Comisión de inventario', 2);
        $other = $plan->activities()->where('activity', 'other')->first();
        $this->service->placeBlock($plan, $other->id, 'monday', '13:00');
        $this->service->placeBlock($plan, $other->id, 'monday', '14:00');

        $this->assertStringContainsString('quite bloques antes de reducir', $this->service->saveActivity($plan, $other->id, 'other', 'Comisión de inventario', 1));

        $this->assertNull($this->service->removeActivity($plan, $other->id));
        $this->assertSame(0, $plan->blocks()->count());
    }

    public function test_complete_plan_has_no_issues(): void
    {
        $this->teacher->update(['workday_type' => 'part_time']);
        $this->lective(16); // preparación 4 → 20 exactas
        $plan = $this->plan();
        $preparation = $this->preparation($plan);
        $this->service->saveActivity($plan, $preparation->id, '', 'Planificación de sesiones', null);
        foreach (['08:00', '09:00', '10:00', '11:00'] as $time) {
            $this->assertNull($this->service->placeBlock($plan, $preparation->id, 'friday', $time));
        }

        $summary = $this->summary();
        $this->assertSame([], $summary['issues']);
        $this->assertTrue($summary['is_complete']);
    }

    // --- Pantalla del docente ---

    public function test_teacher_registers_activity_and_places_block_from_the_page(): void
    {
        $this->lective(10);

        $component = Livewire::actingAs($this->teacher->user)
            ->test(NonTeachingSchedule::class)
            ->assertSee('Preparación de clases')
            ->assertSee('Tiempo completo (40 h)')
            ->call('newActivity')
            ->set('activity', 'research_innovation')
            ->set('sub_activity', 'Proyecto de innovación tecnológica')
            ->set('hours', '3')
            ->call('saveActivity')
            ->assertSet('showForm', false)
            ->assertSee('Proyecto de innovación tecnológica');

        $activity = NonTeachingActivity::where('activity', 'research_innovation')->firstOrFail();
        $this->assertSame(180, $activity->weekly_minutes);
        $this->assertSame($this->teacher->id, $activity->plan->teacher_id);

        $component->call('placeBlock', 'monday', '13:00'); // sin actividad seleccionada: no ubica
        $this->assertSame(0, NonTeachingBlock::count());

        $component->call('selectActivity', $activity->id)->call('placeBlock', 'monday', '13:00');
        $block = NonTeachingBlock::firstOrFail();
        $this->assertSame($activity->id, $block->teacher_non_teaching_activity_id);

        $component->call('removeBlock', $block->id);
        $this->assertSame(0, NonTeachingBlock::count());
    }

    public function test_teacher_cannot_touch_another_teachers_plan(): void
    {
        $this->lective(10);
        $other = Teacher::factory()->create();
        $this->lective(10, $other, 'Otra UD');
        $otherPlan = $this->plan($other);
        $this->service->saveActivity($otherPlan, null, 'other', 'Actividad del otro docente', 2);
        $otherActivity = $otherPlan->activities()->where('activity', 'other')->first();
        $this->service->placeBlock($otherPlan, $otherActivity->id, 'monday', '13:00');
        $otherBlock = $otherPlan->blocks()->first();

        Livewire::actingAs($this->teacher->user)
            ->test(NonTeachingSchedule::class)
            ->assertDontSee('Actividad del otro docente')
            ->call('selectActivity', $otherActivity->id)
            ->assertSet('selectedActivityId', null)
            ->call('removeBlock', $otherBlock->id)
            ->call('removeActivity', $otherActivity->id)
            ->call('editActivity', $otherActivity->id)
            ->assertSet('showForm', false)
            ->set('editingActivityId', $otherActivity->id)
            ->set('activity', 'other')
            ->set('sub_activity', 'Texto cambiado por otro docente')
            ->set('hours', '1')
            ->call('saveActivity');

        $this->assertSame('Actividad del otro docente', $otherActivity->fresh()->sub_activity);
        $this->assertSame(1, $otherPlan->blocks()->count());
        $this->assertNotNull($otherActivity->fresh());
    }

    public function test_route_requires_teacher_role_and_handles_user_without_teacher_profile(): void
    {
        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $this->actingAs($student)->get(route('teacher.non-teaching-schedule'))->assertForbidden();

        Role::firstOrCreate(['name' => 'Administrador']);
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        Livewire::actingAs($admin)
            ->test(NonTeachingSchedule::class)
            ->assertSee('no tiene una ficha de docente')
            ->call('newActivity')
            ->set('activity', 'other')
            ->set('sub_activity', 'Texto válido')
            ->set('hours', '2')
            ->call('saveActivity');

        $this->assertSame(0, NonTeachingPlan::count());
    }

    // --- Ficha del docente ---

    /**
     * career_coordinators existe en producción pero no tiene migración: el listado de
     * docentes la consulta, así que se crea aquí para poder probar el formulario.
     */
    private function teacherManagerAdmin(): User
    {
        if (! Schema::hasTable('career_coordinators')) {
            Schema::create('career_coordinators', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('career_id');
                $table->boolean('is_active')->default(true);
                $table->date('assigned_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Role::firstOrCreate(['name' => 'Coordinador']); // el guardado quita este rol si no es coordinador
        // El formulario exige DNI de 8 dígitos y apellidos
        $this->teacher->user->update(['document_number' => '40506070', 'lastname' => 'Huarcaya Flores']);

        Permission::firstOrCreate(['name' => 'gestionar-docentes']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('gestionar-docentes');

        return $admin;
    }

    public function test_admin_sets_workday_type_in_teacher_form(): void
    {
        $admin = $this->teacherManagerAdmin();

        $this->assertSame('full_time', $this->teacher->fresh()->workday_type);

        Livewire::actingAs($admin)
            ->test(TeacherManager::class)
            ->call('edit', $this->teacher->id)
            ->assertSet('workday_type', 'full_time')
            ->set('workday_type', 'part_time')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('part_time', $this->teacher->fresh()->workday_type);
        $this->assertSame(20, $this->service->workdayHours($this->teacher->fresh()));
    }

    public function test_workday_type_must_be_valid(): void
    {
        $admin = $this->teacherManagerAdmin();

        Livewire::actingAs($admin)
            ->test(TeacherManager::class)
            ->call('edit', $this->teacher->id)
            ->set('workday_type', 'media_jornada')
            ->call('save')
            ->assertHasErrors('workday_type');

        $this->assertSame('full_time', $this->teacher->fresh()->workday_type);
    }
}
