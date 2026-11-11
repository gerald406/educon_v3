<?php

namespace Tests\Unit;

use App\Http\Controllers\Teacher\TeacherDocumentController;
use App\Models\NonTeachingActivity;
use App\Models\Teacher;
use App\Services\NonTeachingScheduleService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Pruebas unitarias (sin base de datos) de las reglas puras del horario no lectivo:
 * Anexo 2, jornada, catálogo de actividades y textos de los documentos.
 */
class NonTeachingRulesTest extends TestCase
{
    private NonTeachingScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NonTeachingScheduleService;
    }

    public function test_annex_two_table_from_four_to_twenty_hours(): void
    {
        // Tabla oficial del ANEXO 2 (docs/archivos/anexo2.JPG)
        $annex = [
            4 => 1, 5 => 1.5, 6 => 1.5, 7 => 2, 8 => 2, 9 => 2.5, 10 => 2.5, 11 => 3, 12 => 3,
            13 => 3.5, 14 => 3.5, 15 => 4, 16 => 4, 17 => 4.5, 18 => 4.5, 19 => 5, 20 => 5,
        ];

        foreach ($annex as $lective => $hours) {
            $this->assertSame((int) ($hours * 60), $this->service->preparationMinutes($lective), "{$lective} horas lectivas");
        }
    }

    public function test_annex_two_progression_above_twenty_hours(): void
    {
        $this->assertSame(5.5 * 60, (float) $this->service->preparationMinutes(21));
        $this->assertSame(6.0 * 60, (float) $this->service->preparationMinutes(24));
        $this->assertSame(9.0 * 60, (float) $this->service->preparationMinutes(35));
    }

    public function test_annex_two_does_not_apply_below_four_hours(): void
    {
        foreach ([0, 1, 2, 3] as $lective) {
            $this->assertSame(0, $this->service->preparationMinutes($lective));
        }
    }

    public function test_preparation_is_always_a_multiple_of_thirty_minutes_and_never_decreases(): void
    {
        $previous = 0;
        foreach (range(4, 40) as $lective) {
            $minutes = $this->service->preparationMinutes($lective);
            $this->assertSame(0, $minutes % 30);
            $this->assertGreaterThanOrEqual($previous, $minutes);
            $previous = $minutes;
        }
    }

    public function test_workday_hours_by_condition(): void
    {
        $this->assertSame(40, $this->service->workdayHours(new Teacher(['workday_type' => 'full_time'])));
        $this->assertSame(20, $this->service->workdayHours(new Teacher(['workday_type' => 'part_time'])));
        // Sin dato o con un valor desconocido: tiempo completo
        $this->assertSame(40, $this->service->workdayHours(new Teacher));
        $this->assertSame(40, $this->service->workdayHours(new Teacher(['workday_type' => 'otro'])));
    }

    public function test_time_helpers(): void
    {
        $this->assertSame(480, $this->service->toMinutes('08:00'));
        $this->assertSame(930, $this->service->toMinutes('15:30'));
        $this->assertSame('08:00', $this->service->toTime(480));
        $this->assertSame('15:30', $this->service->toTime(930));

        $this->assertSame('2', $this->service->formatHours(120));
        $this->assertSame('1.5', $this->service->formatHours(90));
        $this->assertSame('0.5', $this->service->formatHours(30));
        $this->assertSame('0', $this->service->formatHours(0));
    }

    public function test_grid_is_eight_one_hour_slots_from_eight_to_sixteen(): void
    {
        $this->assertSame('08:00', $this->service->toTime(NonTeachingScheduleService::GRID_START));
        $this->assertSame('16:00', $this->service->toTime(NonTeachingScheduleService::GRID_END));
        $this->assertSame(8, (NonTeachingScheduleService::GRID_END - NonTeachingScheduleService::GRID_START) / 60);
        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], array_keys(NonTeachingScheduleService::DAY_LABELS));
    }

    public function test_catalog_has_the_eight_official_activities_with_preparation(): void
    {
        $this->assertCount(8, NonTeachingActivity::ACTIVITIES);
        $this->assertSame('Preparación de clases', NonTeachingActivity::ACTIVITIES[NonTeachingActivity::PREPARATION]);
        // Orden de las plantillas: Preparación es la quinta (letra e)
        $this->assertSame(4, array_search(NonTeachingActivity::PREPARATION, array_keys(NonTeachingActivity::ACTIVITIES), true));
        $this->assertSame(4, NonTeachingScheduleService::MAX_ACTIVITIES);

        $activity = new NonTeachingActivity(['activity' => 'sefsrt']);
        $this->assertSame('S.E.F.S.R.T.', $activity->label());
        $this->assertFalse($activity->isPreparation());
        $this->assertTrue((new NonTeachingActivity(['activity' => NonTeachingActivity::PREPARATION]))->isPreparation());
    }

    public function test_document_texts(): void
    {
        $this->assertSame('PRIMER SEMESTRE', TeacherDocumentController::semesterName('2026-I'));
        $this->assertSame('SEGUNDO SEMESTRE', TeacherDocumentController::semesterName('2027-II'));
        $this->assertSame('PRIMER SEMESTRE', TeacherDocumentController::semesterName('2026-1'));
        $this->assertSame('SEMESTRE', TeacherDocumentController::semesterName('2026-III'));

        $this->assertSame('1 de enero del 2027', TeacherDocumentController::dateText(Carbon::create(2027, 1, 1)));
        $this->assertSame('30 de septiembre del 2026', TeacherDocumentController::dateText(Carbon::create(2026, 9, 30)));
    }
}
