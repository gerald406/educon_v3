<?php

namespace Tests\Feature\Syllabus;

use App\Livewire\Pages\Teacher\LearningSessionEditor;
use App\Livewire\Pages\Teacher\LearningSessionList;
use App\Livewire\Pages\Teacher\SyllabusEditor;
use App\Models\AcademicPeriod;
use App\Models\Institution;
use App\Models\LearningSession;
use App\Models\Shift;
use App\Models\Syllabus;
use App\Models\SyllabusIndicator;
use App\Models\SyllabusUnit;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Services\SyllabusRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Semanas 1-16 regulares + 17-18 de "Reforzamiento y recuperación de capacidades no logradas".
 */
class SyllabusRecoveryWeeksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // TeacherFactory asigna el rol Docente
        Role::firstOrCreate(['name' => 'Docente']);
    }

    private function makeAssignment(string $periodStatus = 'planned'): TeacherAssignment
    {
        Institution::factory()->create(['status' => 'active']);

        return TeacherAssignment::factory()->create([
            'teacher_id' => Teacher::factory(),
            'academic_period_id' => AcademicPeriod::factory()->state(['status' => $periodStatus]),
            'shift_id' => Shift::create([
                'name' => 'Mañana',
                'start_time' => '08:00',
                'end_time' => '13:00',
            ])->id,
        ]);
    }

    /** Sílabo con la estructura anterior: un indicador con N semanas regulares. */
    private function makeLegacySyllabus(TeacherAssignment $assignment, int $weeks): Syllabus
    {
        $syllabus = Syllabus::create([
            'teacher_assignment_id' => $assignment->id,
            'status' => 'draft',
            'version' => '1.0',
        ]);
        $indicator = SyllabusIndicator::create([
            'syllabus_id' => $syllabus->id,
            'description' => 'Indicador 1',
            'sort_order' => 1,
        ]);
        for ($week = 1; $week <= $weeks; $week++) {
            $indicator->units()->create([
                'session_number' => $week,
                'name' => "Tema $week",
                'content' => "Contenido $week",
                'learning_outcome' => "Logro $week",
                'evaluation_instrument' => 'Rúbrica',
            ]);
        }

        return $syllabus;
    }

    private function teacherUser(TeacherAssignment $assignment)
    {
        return $assignment->teacher->user;
    }

    private function assertFixedRecoveryTexts(SyllabusUnit $unit): void
    {
        $this->assertSame(SyllabusUnit::TYPE_RECOVERY, $unit->type);
        $this->assertNull($unit->syllabus_indicator_id);
        $this->assertSame(SyllabusUnit::RECOVERY_ACTIVITY, $unit->name);
        $this->assertSame(SyllabusUnit::RECOVERY_CONTENT, $unit->content);
        $this->assertSame(SyllabusUnit::RECOVERY_LEARNING_OUTCOME, $unit->learning_outcome);
    }

    public function test_new_syllabus_gets_recovery_weeks_17_and_18(): void
    {
        $assignment = $this->makeAssignment('active');

        Livewire::actingAs($this->teacherUser($assignment))
            ->test(SyllabusEditor::class, ['assignment' => $assignment])
            ->assertSet('maxRegularWeeks', 16)
            ->set('activeTab', 'programming')
            ->assertSee(SyllabusUnit::RECOVERY_SECTION_TITLE);

        $syllabus = Syllabus::where('teacher_assignment_id', $assignment->id)->firstOrFail();
        $recovery = $syllabus->recoveryUnits()->get();

        $this->assertSame([17, 18], $recovery->pluck('session_number')->all());
        $recovery->each(function (SyllabusUnit $unit) {
            $this->assertFixedRecoveryTexts($unit);
            $this->assertSame(SyllabusUnit::DEFAULT_INSTRUMENT, $unit->evaluation_instrument);
        });
    }

    public function test_regular_weeks_are_limited_to_16_in_new_structure(): void
    {
        $assignment = $this->makeAssignment('active');
        $component = Livewire::actingAs($this->teacherUser($assignment))
            ->test(SyllabusEditor::class, ['assignment' => $assignment])
            ->call('addIndicator');

        $indicatorId = SyllabusIndicator::firstOrFail()->id;
        for ($i = 0; $i < 17; $i++) {
            $component->call('addSession', $indicatorId);
        }

        $this->assertSame(16, SyllabusUnit::where('syllabus_indicator_id', $indicatorId)->count());
        $this->assertSame(16, SyllabusUnit::where('syllabus_indicator_id', $indicatorId)->max('session_number'));
        $component->assertSet('total_weeks_programmed', 16);
    }

    public function test_legacy_syllabus_of_active_period_keeps_18_regular_weeks(): void
    {
        $assignment = $this->makeAssignment('active');
        $syllabus = $this->makeLegacySyllabus($assignment, 18);

        Livewire::actingAs($this->teacherUser($assignment))
            ->test(SyllabusEditor::class, ['assignment' => $assignment])
            ->assertSet('maxRegularWeeks', 18)
            ->assertSet('total_weeks_programmed', 18);

        $this->assertSame(0, $syllabus->recoveryUnits()->count());
        $this->assertSame(18, SyllabusUnit::where('type', SyllabusUnit::TYPE_REGULAR)->count());
    }

    public function test_legacy_syllabus_of_planned_period_is_converted(): void
    {
        $assignment = $this->makeAssignment('planned');
        $syllabus = $this->makeLegacySyllabus($assignment, 18);
        $week17 = SyllabusUnit::where('session_number', 17)->firstOrFail();
        $session = LearningSession::create(['syllabus_unit_id' => $week17->id] + LearningSession::defaultAttributes());

        Livewire::actingAs($this->teacherUser($assignment))
            ->test(SyllabusEditor::class, ['assignment' => $assignment])
            ->assertSet('maxRegularWeeks', 16)
            ->assertSet('total_weeks_programmed', 16);

        $recovery = $syllabus->recoveryUnits()->get();
        $this->assertCount(2, $recovery);
        $recovery->each(function (SyllabusUnit $unit) {
            $this->assertFixedRecoveryTexts($unit);
            $this->assertSame('Rúbrica', $unit->evaluation_instrument); // se conserva el instrumento
        });
        // Se convierte la misma fila: su sesión de aprendizaje sigue asociada
        $this->assertSame($week17->id, $recovery->firstWhere('session_number', 17)->id);
        $this->assertTrue($session->fresh()->syllabusUnit->isRecovery());
    }

    public function test_ensure_for_is_idempotent_and_reimposes_fixed_texts(): void
    {
        $assignment = $this->makeAssignment('planned');
        $syllabus = Syllabus::create(['teacher_assignment_id' => $assignment->id, 'status' => 'draft']);
        $service = app(SyllabusRecoveryService::class);

        $service->ensureFor($syllabus);
        SyllabusUnit::recovery()->where('session_number', 17)->update([
            'name' => 'Texto alterado',
            'evaluation_instrument' => 'Portafolio',
        ]);
        $service->ensureFor($syllabus);

        $this->assertSame(2, SyllabusUnit::recovery()->count());
        $week17 = SyllabusUnit::recovery()->where('session_number', 17)->firstOrFail();
        $this->assertSame(SyllabusUnit::RECOVERY_ACTIVITY, $week17->name);
        $this->assertSame('Portafolio', $week17->evaluation_instrument);
    }

    public function test_teacher_can_choose_recovery_instrument_only_from_the_list(): void
    {
        $assignment = $this->makeAssignment('active');
        $component = Livewire::actingAs($this->teacherUser($assignment))
            ->test(SyllabusEditor::class, ['assignment' => $assignment]);
        $week18 = SyllabusUnit::recovery()->where('session_number', 18)->firstOrFail();

        $component->call('updateRecoveryInstrument', $week18->id, 'Prueba Escrita');
        $this->assertSame('Prueba Escrita', $week18->fresh()->evaluation_instrument);

        $component->call('updateRecoveryInstrument', $week18->id, 'Cualquier cosa');
        $this->assertSame('Prueba Escrita', $week18->fresh()->evaluation_instrument);
    }

    public function test_recovery_texts_cannot_be_edited_and_weeks_cannot_be_removed(): void
    {
        $assignment = $this->makeAssignment('active');
        $component = Livewire::actingAs($this->teacherUser($assignment))
            ->test(SyllabusEditor::class, ['assignment' => $assignment]);
        $week17 = SyllabusUnit::recovery()->where('session_number', 17)->firstOrFail();

        $component->call('updateUnitField', $week17->id, 'name', 'Otro tema')
            ->call('removeSession', $week17->id);

        $this->assertFixedRecoveryTexts($week17->fresh());
    }

    public function test_recovery_instrument_is_locked_when_syllabus_is_submitted(): void
    {
        $assignment = $this->makeAssignment('active');
        $component = Livewire::actingAs($this->teacherUser($assignment))
            ->test(SyllabusEditor::class, ['assignment' => $assignment]);
        $week17 = SyllabusUnit::recovery()->where('session_number', 17)->firstOrFail();

        Syllabus::query()->update(['status' => 'submitted']);
        $component->call('updateRecoveryInstrument', $week17->id, 'Informe');

        $this->assertSame(SyllabusUnit::DEFAULT_INSTRUMENT, $week17->fresh()->evaluation_instrument);
    }

    public function test_recovery_weeks_belong_to_their_syllabus_for_learning_sessions(): void
    {
        $assignment = $this->makeAssignment('planned');
        $syllabus = Syllabus::create(['teacher_assignment_id' => $assignment->id, 'status' => 'approved']);
        $other = Syllabus::create(['teacher_assignment_id' => $this->makeAssignmentForSameInstitution($assignment)->id, 'status' => 'approved']);
        app(SyllabusRecoveryService::class)->ensureFor($syllabus);
        $week17 = SyllabusUnit::recovery()->where('session_number', 17)->firstOrFail();

        $this->assertTrue(LearningSession::unitBelongsToSyllabus($week17, $syllabus));
        $this->assertFalse(LearningSession::unitBelongsToSyllabus($week17, $other));
    }

    /** Sílabo aprobado con estructura nueva: 1 indicador con 16 semanas + recuperación. */
    private function makeApprovedNewSyllabus(TeacherAssignment $assignment): Syllabus
    {
        $syllabus = $this->makeLegacySyllabus($assignment, 16);
        $syllabus->update(['status' => 'approved']);
        app(SyllabusRecoveryService::class)->ensureFor($syllabus);

        return $syllabus;
    }

    public function test_learning_session_list_includes_recovery_weeks(): void
    {
        $assignment = $this->makeAssignment('active');
        $syllabus = $this->makeApprovedNewSyllabus($assignment);

        Livewire::actingAs($this->teacherUser($assignment))
            ->test(LearningSessionList::class, ['syllabus' => $syllabus])
            ->assertSee(SyllabusUnit::RECOVERY_SECTION_TITLE)
            ->assertSee('0 / 18 sesiones completadas');

        // Se generan las 18 sesiones de aprendizaje (16 regulares + 2 de recuperación)
        $this->assertSame(18, LearningSession::count());
    }

    public function test_learning_session_editor_works_for_recovery_week(): void
    {
        $assignment = $this->makeAssignment('active');
        $syllabus = $this->makeApprovedNewSyllabus($assignment);
        $week18 = SyllabusUnit::recovery()->where('session_number', 18)->firstOrFail();

        Livewire::actingAs($this->teacherUser($assignment))
            ->test(LearningSessionEditor::class, ['syllabus' => $syllabus, 'unit' => $week18])
            ->assertOk()
            ->assertSee(SyllabusUnit::RECOVERY_SECTION_TITLE)
            ->call('saveAndComplete');

        $this->assertSame('completed', $week18->learningSession()->firstOrFail()->status);
    }

    public function test_syllabus_pdf_renders_recovery_section(): void
    {
        $assignment = $this->makeAssignment('active');
        $syllabus = $this->makeApprovedNewSyllabus($assignment);

        $this->actingAs($this->teacherUser($assignment))
            ->get(route('teacher.syllabus.pdf', $syllabus))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $html = view('reports.syllabus-pdf', [
            'syllabus' => $syllabus->fresh()->load(['indicators.units', 'recoveryUnits']),
        ])->render();
        $this->assertStringContainsString(mb_strtoupper(SyllabusUnit::RECOVERY_SECTION_TITLE), $html);
        $this->assertStringContainsString(SyllabusUnit::RECOVERY_LEARNING_OUTCOME, $html);
    }

    private function makeAssignmentForSameInstitution(TeacherAssignment $existing): TeacherAssignment
    {
        return TeacherAssignment::factory()->create([
            'teacher_id' => Teacher::factory(),
            'academic_period_id' => $existing->academic_period_id,
            'shift_id' => Shift::create(['name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '19:00'])->id,
        ]);
    }
}
