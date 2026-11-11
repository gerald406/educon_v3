<?php

namespace Tests\Unit;

use App\Models\Shift;
use App\Services\PedagogicalHourService;
use Tests\TestCase;

/**
 * Pruebas unitarias (sin base de datos) de las franjas oficiales de horas pedagógicas.
 */
class PedagogicalHourServiceTest extends TestCase
{
    private PedagogicalHourService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PedagogicalHourService;
    }

    private function shift(?string $start, ?string $end): Shift
    {
        return new Shift(['name' => 'Turno', 'start_time' => $start, 'end_time' => $end]);
    }

    public function test_official_slots_match_the_templates(): void
    {
        $slots = $this->service->slots();

        $this->assertCount(12, $slots);
        $this->assertSame(['08:00', '08:45'], [$slots[0]['start'], $slots[0]['end']]);
        $this->assertSame(['10:15', '11:00'], [$slots[3]['start'], $slots[3]['end']]);
        $this->assertSame(['11:30', '12:15'], [$slots[4]['start'], $slots[4]['end']]); // después del receso
        $this->assertSame(['12:15', '13:00'], [$slots[5]['start'], $slots[5]['end']]);
        $this->assertSame(['night', '17:00', '17:40'], [$slots[6]['group'], $slots[6]['start'], $slots[6]['end']]);
        $this->assertSame(['20:20', '21:00'], [$slots[11]['start'], $slots[11]['end']]);
    }

    public function test_slots_are_contiguous_except_for_the_morning_break(): void
    {
        foreach (PedagogicalHourService::GROUPS as $group => $definition) {
            foreach ($definition['slots'] as $i => [, $start, $end]) {
                $minutes = (strtotime($end) - strtotime($start)) / 60;
                $this->assertSame($definition['minutes'], (int) $minutes, "{$group} #{$i}");
            }
        }
    }

    public function test_minutes_per_hour_depend_on_the_shift(): void
    {
        $this->assertSame(45, $this->service->minutesPerHour($this->shift('08:00', '13:00')));
        $this->assertSame(45, $this->service->minutesPerHour($this->shift('08:00', '12:30')));
        $this->assertSame(40, $this->service->minutesPerHour($this->shift('17:00', '21:00')));
        $this->assertSame(40, $this->service->minutesPerHour($this->shift('18:30', '22:30')));
        // Fuera de las jornadas oficiales, sin horario o sin turno: hora de 60 minutos
        $this->assertSame(60, $this->service->minutesPerHour($this->shift('13:30', '18:00')));
        $this->assertSame(60, $this->service->minutesPerHour($this->shift(null, null)));
        $this->assertSame(60, $this->service->minutesPerHour(null));
    }

    public function test_group_of_shift(): void
    {
        $this->assertSame('morning', $this->service->groupOfShift($this->shift('08:00', '13:00')));
        $this->assertSame('night', $this->service->groupOfShift($this->shift('17:00', '21:00')));
        $this->assertNull($this->service->groupOfShift($this->shift('13:00', '17:00')));
        $this->assertNull($this->service->groupOfShift($this->shift('07:00', '08:00')));
    }

    public function test_slots_touching_uses_strict_overlap(): void
    {
        $labels = fn (string $start, string $end) => array_map(
            fn (array $s) => $s['group'].':'.$s['label'],
            $this->service->slotsTouching($start, $end)
        );

        $this->assertSame(['morning:1ra', 'morning:2da'], $labels('08:00', '09:30'));
        $this->assertSame(['morning:1ra', 'morning:2da'], $labels('08:10', '09:00'));
        $this->assertSame(['morning:4ta', 'morning:5ta'], $labels('10:15', '12:15')); // cruza el receso
        $this->assertSame([], $labels('11:00', '11:30'));                             // solo el receso
        $this->assertSame([], $labels('14:00', '16:00'));
        $this->assertSame(['night:1ra'], $labels('17:00:00', '17:40:00'));            // acepta H:i:s
        $this->assertSame(['morning:6ta'], $labels('12:15', '13:00'));
    }

    public function test_fits_slots_only_on_exact_slot_borders(): void
    {
        $this->assertTrue($this->service->fitsSlots('08:00', '08:45'));
        $this->assertTrue($this->service->fitsSlots('08:00', '11:00'));
        $this->assertTrue($this->service->fitsSlots('10:15', '12:15')); // 4ta + receso + 5ta
        $this->assertTrue($this->service->fitsSlots('17:40', '19:00'));

        $this->assertFalse($this->service->fitsSlots('08:00', '09:00'));
        $this->assertFalse($this->service->fitsSlots('08:10', '08:45'));
        $this->assertFalse($this->service->fitsSlots('14:00', '15:00'));
        $this->assertFalse($this->service->fitsSlots('12:15', '17:40')); // mezcla jornadas
    }
}
