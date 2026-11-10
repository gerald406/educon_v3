<?php

namespace Tests\Feature\Syllabus;

use App\Livewire\Pages\Teacher\SyllabusEditor;
use App\Models\AcademicPeriod;
use App\Models\Institution;
use App\Models\Shift;
use App\Models\Syllabus;
use App\Models\SyllabusIndicator;
use App\Models\SyllabusUnit;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresiones de la revisión de seguridad de SyllabusEditor (teacher/my-syllabi/{assignment}/edit).
 */
class SyllabusEditorSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Docente']);
    }

    private function makeAssignment(): TeacherAssignment
    {
        Institution::factory()->create(['status' => 'active']);

        return TeacherAssignment::factory()->create([
            'teacher_id' => Teacher::factory(),
            'academic_period_id' => AcademicPeriod::factory()->state(['status' => 'active']),
            'shift_id' => Shift::create(['name' => 'Mañana', 'start_time' => '08:00', 'end_time' => '13:00'])->id,
        ]);
    }

    private function editor(TeacherAssignment $assignment)
    {
        return Livewire::actingAs($assignment->teacher->user)
            ->test(SyllabusEditor::class, ['assignment' => $assignment]);
    }

    private function indicatorOf(TeacherAssignment $assignment): SyllabusIndicator
    {
        return SyllabusIndicator::create([
            'syllabus_id' => Syllabus::where('teacher_assignment_id', $assignment->id)->value('id'),
            'description' => 'Indicador 1',
            'sort_order' => 1,
        ]);
    }

    // --- Punto 1: el límite de 16 semanas no depende de propiedades manipulables ---

    public function test_week_counters_cannot_be_changed_from_the_browser(): void
    {
        $component = $this->editor($this->makeAssignment());

        foreach (['maxRegularWeeks' => 99, 'total_weeks_programmed' => -50] as $property => $value) {
            try {
                $component->set($property, $value);
                $this->fail("Se pudo modificar {$property} desde el cliente.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_add_session_counts_weeks_from_database(): void
    {
        $assignment = $this->makeAssignment();
        $component = $this->editor($assignment);
        $indicator = $this->indicatorOf($assignment);

        // Otra pestaña programó las 16 semanas: el estado en memoria del componente quedó desactualizado
        for ($week = 1; $week <= 16; $week++) {
            $indicator->units()->create(['session_number' => $week, 'name' => "Tema $week", 'evaluation_instrument' => 'Rúbrica']);
        }

        $component->call('addSession', $indicator->id);

        $this->assertSame(16, SyllabusUnit::where('type', SyllabusUnit::TYPE_REGULAR)->count());
        $this->assertSame(16, SyllabusUnit::where('type', SyllabusUnit::TYPE_REGULAR)->max('session_number'));
    }

    // --- Punto 2: no se puede enviar un sílabo incompleto ---

    public function test_submit_syllabus_called_directly_rejects_incomplete_syllabus(): void
    {
        $assignment = $this->makeAssignment();

        $this->editor($assignment)
            ->call('submitSyllabus')
            ->assertNoRedirect();

        $this->assertSame('draft', Syllabus::where('teacher_assignment_id', $assignment->id)->value('status'));
    }

    public function test_submission_validates_saved_content_not_unsaved_editor_text(): void
    {
        $assignment = $this->makeAssignment();
        $component = $this->editor($assignment);
        $indicator = $this->indicatorOf($assignment);
        $component->call('loadIndicatorsWithUnits')->call('addSession', $indicator->id);

        // Escrito en los editores pero nunca guardado (la evaluación trae un texto por defecto sin guardar)
        $component->call('handleSumillaUpdate', '<p>Sumilla sin guardar</p>')
            ->call('handleMethodologyUpdate', '<p>Metodología sin guardar</p>')
            ->call('confirmSubmit')
            ->assertSet('confirmingSubmission', false)
            ->call('submitSyllabus');

        $this->assertSame('draft', Syllabus::where('teacher_assignment_id', $assignment->id)->value('status'));
    }

    public function test_complete_saved_syllabus_can_be_submitted(): void
    {
        $assignment = $this->makeAssignment();
        $component = $this->editor($assignment);
        $indicator = $this->indicatorOf($assignment);
        $component->call('loadIndicatorsWithUnits')->call('addSession', $indicator->id)
            ->call('handleSumillaUpdate', '<p>Sumilla</p>')->call('saveSumilla')
            ->call('handleMethodologyUpdate', '<p>Metodología</p>')->call('saveMethodology')
            ->call('saveEvaluation')
            ->call('confirmSubmit')
            ->assertSet('confirmingSubmission', true)
            ->call('submitSyllabus')
            ->assertRedirect(route('teacher.my-syllabi'));

        $this->assertSame('submitted', Syllabus::where('teacher_assignment_id', $assignment->id)->value('status'));
    }

    // --- Punto 3: Administrador/Coordinador sin perfil docente ---

    public function test_user_without_teacher_profile_gets_403_instead_of_500(): void
    {
        $assignment = $this->makeAssignment();
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(SyllabusEditor::class, ['assignment' => $assignment])
            ->assertForbidden();

        $this->assertSame(0, Syllabus::count());
    }

    // --- Punto 4: abrir el editor no modifica sílabos enviados/aprobados ---
    // Cubierto en SyllabusRecoveryWeeksTest: test_legacy_approved_syllabus_of_planned_period_keeps_18_regular_weeks
    // y test_legacy_submitted_syllabus_of_active_period_keeps_18_regular_weeks.

    // --- Punto 5: los errores no exponen detalles técnicos ---

    public function test_save_errors_show_generic_message(): void
    {
        $assignment = $this->makeAssignment();
        $component = $this->editor($assignment);

        Syllabus::saving(fn () => throw new \RuntimeException('SQLSTATE[HY000]: detalle interno'));

        $component->call('handleSumillaUpdate', '<p>Texto</p>')
            ->call('saveSumilla')
            ->assertDispatched('swal', function ($event, $params) {
                $text = $params[0]['text'] ?? '';

                return ($params[0]['icon'] ?? null) === 'error'
                    && $text === 'No se pudo guardar. Intente nuevamente.';
            });
    }

    public function test_editor_handlers_only_accept_text(): void
    {
        $assignment = $this->makeAssignment();

        $this->editor($assignment)
            ->call('handleSumillaUpdate', ['no' => 'es texto'])
            ->assertSet('sumilla', '')
            ->call('saveSumilla');

        $this->assertSame('', (string) Syllabus::where('teacher_assignment_id', $assignment->id)->value('sumilla'));
    }
}
