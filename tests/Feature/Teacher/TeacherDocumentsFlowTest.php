<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Pages\AcademicProcess\Schedules\ScheduleManager;
use App\Livewire\Pages\People\Teachers\TeacherManager;
use App\Livewire\Pages\Teacher\MySchedule;
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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Prueba de integración de punta a punta:
 * administración (ficha del docente + horario lectivo) → docente (horario personal y
 * horario no lectivo) → tres documentos oficiales. También comprueba que los módulos
 * administrativos siguen funcionando con los datos nuevos.
 */
class TeacherDocumentsFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicPeriod $period;

    private Career $career;

    private Module $module;

    private Shift $morning;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Docente', 'Coordinador', 'Administrador'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
        foreach (['gestionar-horarios', 'gestionar-docentes'] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        Institution::factory()->create(['status' => 'active']);

        // career_coordinators existe en producción pero no tiene migración
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

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['gestionar-horarios', 'gestionar-docentes']);

        $this->period = AcademicPeriod::factory()->create(['status' => 'active', 'code' => '2026-I']);
        $this->career = Career::factory()->create(['name' => 'Electricidad Industrial', 'short_name' => 'Electricidad Ind.']);
        $this->module = Module::factory()->create([
            'study_plan_id' => StudyPlan::factory()->create(['career_id' => $this->career->id])->id,
        ]);
        $this->morning = Shift::create(['name' => 'Diurno', 'start_time' => '08:00', 'end_time' => '13:00']);

        $this->teacher = Teacher::factory()->create(['contract_type' => 'contracted']);
        $this->teacher->user->update(['name' => 'Rosa', 'lastname' => 'Quispe Mamani', 'document_number' => '40506070']);
    }

    private function assign(string $name, int $hours, int $theory, int $practice): TeacherAssignment
    {
        $unit = DidacticUnit::factory()->create([
            'module_id' => $this->module->id, 'name' => $name, 'semester' => 3, 'status' => 'active',
            'weekly_hours' => $hours, 'theory_hours' => $theory, 'practice_hours' => $practice,
        ]);

        return TeacherAssignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'academic_period_id' => $this->period->id,
            'didactic_unit_id' => $unit->id,
            'shift_id' => $this->morning->id,
            'section' => 'A',
            'status' => 'active',
        ]);
    }

    public function test_full_flow_from_administration_to_official_documents(): void
    {
        // 1. Administración: condición laboral y programa de adscripción en la ficha del docente
        Livewire::actingAs($this->admin)
            ->test(TeacherManager::class)
            ->call('edit', $this->teacher->id)
            ->set('workday_type', 'part_time')
            ->set('career_id', $this->career->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->teacher->refresh();
        $this->assertSame(['part_time', $this->career->id, 'contracted'], [$this->teacher->workday_type, $this->teacher->career_id, $this->teacher->contract_type]);

        // 2. Administración: carga académica (6 + 6 = 12 horas lectivas) y horario lectivo
        $networks = $this->assign('Redes', 6, 2, 4);
        $this->assign('Ofimática', 6, 3, 3);

        $manager = Livewire::actingAs($this->admin)
            ->test(ScheduleManager::class)
            ->set('selectedCareerId', $this->career->id)
            ->set('selectedTeacherId', $this->teacher->id)
            ->call('selectAssignment', $networks->id);

        foreach ([['monday', '08:00', '09:30', 'T'], ['monday', '09:30', '11:00', 'P'], ['wednesday', '08:00', '09:30', 'P']] as [$day, $start, $end, $type]) {
            $manager->set('day_of_week', $day)->set('start_time', $start)->set('end_time', $end)->set('session_type', $type)
                ->call('saveSchedule')
                ->assertDispatched('swal', fn ($e, $p) => ($p[0]['icon'] ?? null) === 'success');
        }
        $this->assertSame(3, Schedule::where('teacher_assignment_id', $networks->id)->count());

        // 6 horas pedagógicas de 45 min completan la UD: una franja más se rechaza
        $manager->set('day_of_week', 'friday')->set('start_time', '08:00')->set('end_time', '08:45')
            ->call('saveSchedule')
            ->assertDispatched('swal', fn ($e, $p) => str_contains($p[0]['text'] ?? '', 'quedan 0 h'));
        $this->assertSame(3, Schedule::count());

        // 3. Docente: horario personal y su Excel
        $user = $this->teacher->user;
        Livewire::actingAs($user)
            ->test(MySchedule::class)
            ->assertSee('QUISPE MAMANI, ROSA')
            ->assertSee('Redes (T) - Electricidad Ind.')
            ->assertSee('III-DIURNO')
            ->assertSee('Ofimática - Sección A: tiene 0 h programadas de 6 h semanales'); // aviso, no bloquea

        $this->actingAs($user)->get(route('teacher.my-schedule.personal-excel'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // 4. Docente: horario no lectivo. Tiempo parcial 20 h = 12 lectivas + 3 preparación (Anexo 2) + 5
        $page = Livewire::actingAs($user)
            ->test(NonTeachingSchedule::class)
            ->assertSee('Tiempo parcial (20 h)')
            ->assertSee('Faltan 5 h');

        $plan = NonTeachingPlan::where('teacher_id', $this->teacher->id)->firstOrFail();
        $preparation = $plan->activities()->where('activity', NonTeachingActivity::PREPARATION)->firstOrFail();
        $this->assertSame([20, 180], [$plan->workday_hours, $preparation->weekly_minutes]);

        // Todavía incompleto: los documentos no se descargan
        $this->actingAs($user)->get(route('teacher.non-teaching-schedule.excel'))
            ->assertRedirect(route('teacher.non-teaching-schedule'));

        $page->call('editActivity', $preparation->id)
            ->set('sub_activity', 'Elaboración de sesiones de aprendizaje')
            ->call('saveActivity')
            ->call('newActivity')
            ->set('activity', 'student_support')
            ->set('sub_activity', 'Tutoría y consejería de la sección III-A')
            ->set('hours', '5.5')
            ->call('saveActivity')
            ->assertDispatched('swal', fn ($e, $p) => str_contains($p[0]['text'] ?? '', 'hasta 5 h')) // supera la jornada
            ->set('hours', '5')
            ->call('saveActivity')
            ->assertSet('showForm', false);

        $support = $plan->activities()->where('activity', 'student_support')->firstOrFail();

        // El lunes de 08:00 a 11:00 tiene clase: esas horas están bloqueadas
        $page->call('selectActivity', $preparation->id)
            ->call('placeBlock', 'monday', '08:00')
            ->assertDispatched('swal', fn ($e, $p) => str_contains($p[0]['text'] ?? '', 'tiene clase de Redes'));
        $this->assertSame(0, NonTeachingBlock::count());

        foreach (['11:00', '12:00', '13:00'] as $time) {
            $page->call('placeBlock', 'monday', $time);
        }
        $page->call('selectActivity', $support->id);
        foreach (['08:00', '09:00', '10:00', '11:00', '12:00'] as $time) {
            $page->call('placeBlock', 'tuesday', $time);
        }

        $this->assertSame(8, $plan->blocks()->count());
        $page->assertSee('Completo')->assertSee(route('teacher.non-teaching-schedule.excel'));

        // 5. Documentos oficiales
        $this->actingAs($user)->get(route('teacher.non-teaching-schedule.excel'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($user)->get(route('teacher.non-teaching-schedule.activity-program-pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // 6. El área académica programa después una clase encima de una hora no lectiva:
        //    el cambio lectivo se acepta, el bloque se marca y los documentos se bloquean
        $this->actingAs($this->admin); // vuelve a actuar el personal administrativo
        $manager->set('day_of_week', 'tuesday')->set('start_time', '08:00')->set('end_time', '08:45')->set('session_type', '');
        Schedule::where('teacher_assignment_id', $networks->id)->where('day_of_week', 'wednesday')->delete();
        $manager->call('saveSchedule')->assertDispatched('swal', fn ($e, $p) => ($p[0]['icon'] ?? null) === 'success');

        Livewire::actingAs($user)
            ->test(NonTeachingSchedule::class)
            ->assertSee('ahora tiene clase de Redes')
            ->assertSee('Sin cruces con sus clases')
            ->assertDontSee(route('teacher.non-teaching-schedule.excel'));
        $this->assertSame(8, $plan->blocks()->count()); // no se borra nada
        $this->actingAs($user)->get(route('teacher.non-teaching-schedule.activity-program-pdf'))
            ->assertRedirect(route('teacher.non-teaching-schedule'));
    }

    public function test_existing_teacher_pages_and_admin_export_still_work(): void
    {
        $a = $this->assign('Redes', 6, 2, 4);
        Schedule::create(['teacher_assignment_id' => $a->id, 'day_of_week' => 'monday', 'start_time' => '08:00', 'end_time' => '09:30', 'session_type' => 'T']);

        // Páginas del docente
        $user = $this->teacher->user;
        foreach (['teacher.my-schedule', 'teacher.non-teaching-schedule', 'teacher.my-syllabi'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }

        // Reporte PDF de horario por docente del módulo administrativo (ya existente)
        $this->actingAs($this->admin)->get(route('academic-process.schedules.export', [
            'type' => 'teacher', 'format' => 'pdf', 'id' => $this->teacher->id, 'period_id' => $this->period->id,
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Gestión de horarios y listado de docentes siguen cargando
        Livewire::actingAs($this->admin)->test(ScheduleManager::class)
            ->set('selectedCareerId', $this->career->id)
            ->set('selectedTeacherId', $this->teacher->id)
            ->assertSee('Redes')
            ->assertSee('(T)');
        Livewire::actingAs($this->admin)->test(TeacherManager::class)
            ->assertSee('Tiempo completo (40 h)');
    }

    public function test_deleting_the_adscription_program_does_not_delete_the_teacher(): void
    {
        $other = Career::factory()->create();
        $this->teacher->update(['career_id' => $other->id]);

        $other->forceDelete();

        $this->assertNotNull($this->teacher->fresh());
        $this->assertNull($this->teacher->fresh()->career_id);
    }

    public function test_deleting_a_teacher_plan_cascades_to_activities_and_blocks(): void
    {
        $this->assign('Redes', 8, 4, 4);
        $service = app(\App\Services\NonTeachingScheduleService::class);
        $plan = $service->ensurePlan($this->teacher, $this->period);
        $preparation = $plan->activities()->first();
        $this->assertNull($service->placeBlock($plan, $preparation->id, 'friday', '13:00'));

        $plan->delete();

        $this->assertSame(0, NonTeachingActivity::count());
        $this->assertSame(0, NonTeachingBlock::count());
        // La carga y el horario lectivo no se tocan
        $this->assertSame(1, TeacherAssignment::count());
    }
}
