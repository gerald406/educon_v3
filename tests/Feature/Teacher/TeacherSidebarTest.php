<?php

namespace Tests\Feature\Teacher;

use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sidebar del docente: quién lo ve, qué opciones muestra según permisos y que no
 * sustituye al sidebar administrativo ni aparece para otros usuarios.
 */
class TeacherSidebarTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Docente', 'Coordinador', 'Administrador', 'Estudiante'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
        foreach (['subir-silabo', 'aprobar-silabos', 'gestionar-docentes', 'registrar-notas'] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        Institution::factory()->create(['status' => 'active']);

        $this->teacher = Teacher::factory()->create();
    }

    public function test_teacher_uses_teacher_sidebar(): void
    {
        $user = $this->teacher->user;

        $this->assertTrue($user->usesTeacherSidebar());
        $this->assertFalse($user->hasAdminAccess());

        $this->actingAs($user)->get(route('teacher.my-schedule'))
            ->assertOk()
            ->assertSee('Panel del docente')
            ->assertSee('aria-label="Ocultar menú lateral"', false)
            ->assertSee('with-sidebar', false)
            ->assertSee(route('teacher.my-schedule'))
            ->assertSee(route('teacher.non-teaching-schedule'))
            ->assertDontSee('Módulos'); // encabezado del sidebar administrativo
    }

    public function test_sidebar_options_follow_permissions(): void
    {
        $user = $this->teacher->user;

        $this->actingAs($user)->get(route('teacher.my-schedule'))
            ->assertDontSee(route('teacher.my-syllabi'))
            ->assertDontSee('Aprobar Sílabos');

        $user->givePermissionTo('subir-silabo');
        $this->actingAs($user->fresh())->get(route('teacher.my-schedule'))
            ->assertSee(route('teacher.my-syllabi'))
            ->assertSee('Mis Sílabos')
            ->assertDontSee('Aprobar Sílabos');

        $user->givePermissionTo('aprobar-silabos');
        $this->actingAs($user->fresh())->get(route('teacher.my-schedule'))
            ->assertSee('Aprobar Sílabos');
    }

    public function test_unpublished_options_stay_hidden_even_with_permission(): void
    {
        $user = $this->teacher->user;
        $user->givePermissionTo('registrar-notas');

        $this->actingAs($user->fresh())->get(route('teacher.my-schedule'))
            ->assertDontSee('Registro de Notas');
    }

    public function test_coordinator_without_teacher_profile_sees_sidebar_without_schedule_options(): void
    {
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinador');
        $coordinator->givePermissionTo('aprobar-silabos');

        $this->assertTrue($coordinator->usesTeacherSidebar());

        $this->actingAs($coordinator)->get(route('teacher.my-schedule'))
            ->assertOk()
            ->assertSee('Panel del docente')
            ->assertSee('Aprobar Sílabos')
            ->assertDontSee('Horario No Lectivo');
    }

    public function test_administrator_keeps_admin_sidebar(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        $admin->givePermissionTo('gestionar-docentes');

        $this->assertTrue($admin->hasAdminAccess());
        $this->assertFalse($admin->usesTeacherSidebar());

        // Un administrador que además es docente conserva el sidebar administrativo
        $admin->assignRole('Docente');
        $this->assertFalse($admin->fresh()->usesTeacherSidebar());

        $this->actingAs($admin->fresh())->get(route('teacher.my-schedule'))
            ->assertOk()
            ->assertSee('Módulos')
            ->assertDontSee('Panel del docente');
    }

    public function test_student_has_no_sidebar(): void
    {
        $student = User::factory()->create();
        $student->assignRole('Estudiante');

        $this->assertFalse($student->usesTeacherSidebar());

        $this->actingAs($student)->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('Panel del docente')
            ->assertDontSee('aria-label="Ocultar menú lateral"', false);
    }
}
