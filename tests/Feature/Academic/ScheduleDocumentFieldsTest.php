<?php

namespace Tests\Feature\Academic;

use App\Livewire\Pages\Academic\Careers\CareerManager;
use App\Livewire\Pages\Academic\DidacticUnits\DidacticUnitManager;
use App\Models\Career;
use App\Models\DidacticUnit;
use App\Models\Institution;
use App\Models\Module;
use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Datos nuevos que alimentan los horarios oficiales: horas de teoría/práctica de la UD
 * y nombre corto del programa de estudios.
 */
class ScheduleDocumentFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DidacticUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'gestionar-estructura-academica']);
        Institution::factory()->create(['status' => 'active']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('gestionar-estructura-academica');

        $career = Career::factory()->create();
        $module = Module::factory()->create([
            'study_plan_id' => StudyPlan::factory()->create(['career_id' => $career->id])->id,
        ]);
        $this->unit = DidacticUnit::factory()->create(['module_id' => $module->id, 'weekly_hours' => 4]);
    }

    private function editUnit()
    {
        return Livewire::actingAs($this->user)
            ->test(DidacticUnitManager::class)
            ->call('edit', $this->unit->id);
    }

    public function test_unit_saves_theory_and_practice_hours(): void
    {
        $this->editUnit()
            ->set('theory_hours', 1)
            ->set('practice_hours', 3)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([1, 3], [$this->unit->fresh()->theory_hours, $this->unit->fresh()->practice_hours]);
    }

    public function test_unit_breakdown_must_add_up_to_weekly_hours(): void
    {
        $this->editUnit()
            ->set('theory_hours', 1)
            ->set('practice_hours', 2)
            ->call('save')
            ->assertHasErrors('practice_hours');

        $this->assertNull($this->unit->fresh()->theory_hours);
    }

    public function test_unit_breakdown_requires_both_values(): void
    {
        $this->editUnit()
            ->set('theory_hours', 4)
            ->set('practice_hours', '')
            ->call('save')
            ->assertHasErrors('practice_hours');
    }

    public function test_unit_breakdown_is_optional_and_can_be_cleared(): void
    {
        $this->unit->update(['theory_hours' => 2, 'practice_hours' => 2]);

        $this->editUnit()
            ->assertSet('theory_hours', 2)
            ->set('theory_hours', '')
            ->set('practice_hours', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->unit->fresh()->theory_hours);
        $this->assertNull($this->unit->fresh()->practice_hours);
    }

    public function test_unit_changes_require_permission(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(DidacticUnitManager::class)
            ->set('theory_hours', 1)
            ->call('save')
            ->assertForbidden();
    }

    public function test_career_short_name_is_saved_and_empty_becomes_null(): void
    {
        $career = Career::factory()->create(['degree_awarded' => 'Profesional Técnico', 'authorization_resolution' => 'RD 001']);

        $component = Livewire::actingAs($this->user)
            ->test(CareerManager::class)
            ->call('edit', $career->id)
            ->set('short_name', 'Electricidad Ind.')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Electricidad Ind.', $career->fresh()->short_name);

        $component->call('edit', $career->id)
            ->assertSet('short_name', 'Electricidad Ind.')
            ->set('short_name', '  ')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNull($career->fresh()->short_name);
    }
}
