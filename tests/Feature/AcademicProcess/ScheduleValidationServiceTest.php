<?php

namespace Tests\Feature\AcademicProcess;

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
use App\Services\ScheduleValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reglas de bloques horarios: turno de la sección, horas semanales de la UD y cruces.
 */
class ScheduleValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;

    private Career $career;

    private Module $module;

    private Shift $shift;

    private ScheduleValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Docente']);
        Institution::factory()->create(['status' => 'active']);

        $this->period = AcademicPeriod::factory()->create(['status' => 'active']);
        $this->career = Career::factory()->create();
        $this->module = $this->moduleOf($this->career);
        $this->shift = Shift::create(['name' => 'Diurno', 'start_time' => '08:00', 'end_time' => '12:30']);
        $this->service = app(ScheduleValidationService::class);
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

    // --- Turno ---

    public function test_block_inside_shift_borders_is_valid(): void
    {
        $a = $this->section($this->unit(['weekly_hours' => 6]));

        $this->assertNull($this->service->validate($a, 'monday', '08:00', '10:00', null));
        $this->assertNull($this->service->validate($a, 'monday', '10:30', '12:30', null));
    }

    public function test_block_starting_before_shift_is_rejected(): void
    {
        $a = $this->section($this->unit());

        $this->assertSame('El turno Diurno es de 08:00 a 12:30.', $this->service->validate($a, 'monday', '07:30', '09:00', null));
    }

    public function test_block_ending_after_shift_is_rejected(): void
    {
        $a = $this->section($this->unit());

        $this->assertSame('El turno Diurno es de 08:00 a 12:30.', $this->service->validate($a, 'monday', '11:00', '13:00', null));
    }

    public function test_shift_without_hours_does_not_block(): void
    {
        $a = $this->section($this->unit());
        $a->setRelation('shift', new Shift(['name' => 'Sin horario']));

        $this->assertNull($this->service->isWithinShift($a, '19:00', '20:00'));
        $this->assertNull($this->service->validate($a, 'monday', '19:00', '20:00', null));
        $this->assertContains('El turno de la sección no tiene horario registrado.', $this->service->warnings($a));
    }

    // --- Horas semanales (horas pedagógicas: 45 min en la mañana, 40 min en la noche) ---

    public function test_block_completing_weekly_hours_is_valid(): void
    {
        $a = $this->section($this->unit());
        $this->block($a, 'monday', '08:00', '10:15'); // 3 franjas de 45 min

        $this->assertSame(45, $this->service->remainingMinutes($a));
        $this->assertNull($this->service->validate($a, 'tuesday', '10:15', '11:00', null));
    }

    public function test_block_exceeding_weekly_hours_is_rejected(): void
    {
        $a = $this->section($this->unit());
        $this->block($a, 'monday', '08:00', '10:15');

        $this->assertSame(
            'La UD tiene 4 h semanales; ya hay 3 h programadas, quedan 1 h.',
            $this->service->validate($a, 'tuesday', '08:00', '09:30', null)
        );
    }

    public function test_hours_message_formats_fractions(): void
    {
        // Turno fuera de las jornadas oficiales: se mantiene la hora de 60 min
        $afternoon = Shift::create(['name' => 'Tarde', 'start_time' => '13:30', 'end_time' => '18:00']);
        $a = $this->section($this->unit(), ['shift_id' => $afternoon->id]);
        $this->block($a, 'monday', '13:30', '16:00');

        $this->assertSame(60, $this->service->minutesPerHour($a));
        $this->assertSame(
            'La UD tiene 4 h semanales; ya hay 2.5 h programadas, quedan 1.5 h.',
            $this->service->validate($a, 'tuesday', '13:30', '15:30', null)
        );
    }

    public function test_editing_block_is_not_counted_twice(): void
    {
        $a = $this->section($this->unit());
        $existing = $this->block($a, 'monday', '08:00', '11:00'); // 4 franjas de 45 min

        $this->assertSame(180, $this->service->scheduledMinutes($a));
        $this->assertSame(0, $this->service->scheduledMinutes($a, $existing->id));
        $this->assertNull($this->service->validate($a, 'monday', '08:00', '11:00', null, $existing->id));
    }

    public function test_morning_break_does_not_count_as_teaching_time(): void
    {
        $a = $this->section($this->unit());

        // 4ta (10:15-11:00) + receso + 5ta (11:30-12:15) = 2 horas pedagógicas
        $this->assertSame(90, $this->service->blockMinutes($a, '10:15', '12:15'));
        $this->block($a, 'monday', '10:15', '12:15');
        $this->assertSame(90, $this->service->remainingMinutes($a));
    }

    public function test_night_shift_uses_forty_minute_hours(): void
    {
        $night = Shift::create(['name' => 'Nocturno', 'start_time' => '17:00', 'end_time' => '21:00']);
        $a = $this->section($this->unit(), ['shift_id' => $night->id]);

        $this->assertSame(40, $this->service->minutesPerHour($a));
        $this->assertSame(160, $this->service->weeklyMinutes($a));

        // 4 franjas de 40 min completan las 4 h; una quinta se rechaza
        $this->assertNull($this->service->validate($a, 'monday', '17:00', '19:40', null));
        $this->block($a, 'monday', '17:00', '19:40');
        $this->assertSame(
            'La UD tiene 4 h semanales; ya hay 4 h programadas, quedan 0 h.',
            $this->service->validate($a, 'tuesday', '17:00', '17:40', null)
        );
    }

    public function test_block_not_aligned_counts_every_slot_it_touches(): void
    {
        $a = $this->section($this->unit());

        // 08:10-09:00 toca la 1ra y la 2da franja
        $this->assertSame(90, $this->service->blockMinutes($a, '08:10', '09:00'));
    }

    public function test_saturday_is_not_a_schedulable_day(): void
    {
        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], ScheduleValidationService::DAYS);
    }

    public function test_unit_without_weekly_hours_does_not_block(): void
    {
        $a = $this->section($this->unit(['weekly_hours' => 0]));
        $this->block($a, 'monday', '08:00', '12:30');

        $this->assertNull($this->service->remainingMinutes($a));
        $this->assertNull($this->service->validate($a, 'tuesday', '08:00', '12:30', null));
        $this->assertContains('La UD no tiene horas semanales registradas.', $this->service->warnings($a));
    }

    public function test_sections_have_independent_hours(): void
    {
        $unit = $this->unit();
        $a = $this->section($unit);
        $b = $this->section($unit, ['section' => 'B']);
        $this->block($a, 'monday', '08:00', '11:00');

        $this->assertNull($this->service->validate($b, 'tuesday', '08:00', '11:00', null));
    }

    // --- Cruces ---

    public function test_overlap_in_same_section_is_rejected(): void
    {
        $a = $this->section($this->unit(['weekly_hours' => 6]));
        $this->block($a, 'monday', '08:00', '09:30');

        $this->assertStringStartsWith('Esta sección ya tiene un bloque', $this->service->validate($a, 'monday', '08:45', '10:15', null));
    }

    public function test_teacher_overlap_in_another_program_is_rejected(): void
    {
        $teacher = Teacher::factory()->create();
        $otherProgramUnit = DidacticUnit::factory()->create([
            'module_id' => $this->moduleOf(Career::factory()->create())->id,
            'semester' => 5, 'weekly_hours' => 4, 'status' => 'active',
        ]);
        $other = $this->section($otherProgramUnit, ['teacher_id' => $teacher->id]);
        $this->block($other, 'monday', '08:00', '10:00');

        $mine = $this->section($this->unit(), ['teacher_id' => $teacher->id]);

        $this->assertStringStartsWith('El docente ya tiene clase', $this->service->validate($mine, 'monday', '09:00', '11:00', null));
    }

    public function test_student_group_overlap_is_rejected(): void
    {
        $a = $this->section($this->unit());
        $this->block($a, 'monday', '08:00', '10:00');
        $b = $this->section($this->unit());

        $this->assertStringStartsWith('Los estudiantes del semestre 1, sección A', $this->service->validate($b, 'monday', '08:00', '10:00', null));
    }

    public function test_classroom_overlap_is_rejected(): void
    {
        $room = ClassroomResource::factory()->create();
        $this->block($this->section($this->unit()), 'monday', '08:00', '10:00', $room->id);
        $other = $this->section($this->unit(['semester' => 3]));

        $this->assertStringStartsWith('El aula', $this->service->validate($other, 'monday', '09:00', '11:00', $room->id));
    }

    public function test_consecutive_blocks_do_not_conflict(): void
    {
        $teacher = Teacher::factory()->create();
        $this->block($this->section($this->unit(), ['teacher_id' => $teacher->id]), 'monday', '08:00', '10:00');
        $next = $this->section($this->unit(['semester' => 3]), ['teacher_id' => $teacher->id]);

        $this->assertNull($this->service->validate($next, 'monday', '10:00', '12:00', null));
    }

    public function test_other_period_and_inactive_assignments_are_ignored(): void
    {
        $teacher = Teacher::factory()->create();
        $planned = AcademicPeriod::factory()->create(['status' => 'planned', 'code' => 'NEXT-I']);
        $this->block($this->section($this->unit(), ['teacher_id' => $teacher->id, 'academic_period_id' => $planned->id]), 'monday', '08:00', '10:00');
        $this->block($this->section($this->unit(), ['teacher_id' => $teacher->id, 'status' => 'suspended']), 'monday', '08:00', '10:00');

        $mine = $this->section($this->unit(['semester' => 3]), ['teacher_id' => $teacher->id]);

        $this->assertNull($this->service->validate($mine, 'monday', '08:00', '10:00', null));
    }

    public function test_unavailable_classroom_is_rejected(): void
    {
        $room = ClassroomResource::factory()->create(['status' => 'maintenance']);
        $a = $this->section($this->unit());

        $this->assertSame('El aula seleccionada no está disponible.', $this->service->validate($a, 'monday', '08:00', '10:00', $room->id));
    }
}
