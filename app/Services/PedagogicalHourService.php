<?php

namespace App\Services;

use App\Casts\TimeCast;
use App\Models\Shift;

/**
 * [NUEVO] Franjas horarias oficiales (horas pedagógicas) del instituto.
 *
 * Son las filas de las plantillas oficiales de horario: mañana con horas de 45 min
 * (receso de 11:00 a 11:30) y noche con horas de 40 min. Una "hora semanal" de una UD
 * equivale a una franja del turno de su sección.
 *
 * Si el horario de un turno no cae en ninguna jornada se mantiene la hora de 60 min.
 */
class PedagogicalHourService
{
    public const DEFAULT_MINUTES = 60;

    /** Jornadas en el orden en que aparecen en las plantillas. */
    public const GROUPS = [
        'morning' => [
            'label' => 'Mañana',
            'minutes' => 45,
            'slots' => [
                ['1ra', '08:00', '08:45'],
                ['2da', '08:45', '09:30'],
                ['3ra', '09:30', '10:15'],
                ['4ta', '10:15', '11:00'],
                ['5ta', '11:30', '12:15'],
                ['6ta', '12:15', '13:00'],
            ],
        ],
        'night' => [
            'label' => 'Noche',
            'minutes' => 40,
            'slots' => [
                ['1ra', '17:00', '17:40'],
                ['2da', '17:40', '18:20'],
                ['3ra', '18:20', '19:00'],
                ['4ta', '19:00', '19:40'],
                ['5ta', '19:40', '20:20'],
                ['6ta', '20:20', '21:00'],
            ],
        ],
    ];

    /**
     * Franjas de todas las jornadas.
     *
     * @return array<int, array{group: string, index: int, label: string, start: string, end: string}>
     */
    public function slots(): array
    {
        $slots = [];
        foreach (self::GROUPS as $group => $definition) {
            foreach ($definition['slots'] as $index => [$label, $start, $end]) {
                $slots[] = compact('group', 'index', 'label', 'start', 'end');
            }
        }

        return $slots;
    }

    /** Jornada ('morning' | 'night') en la que empieza el turno; null si no corresponde a ninguna. */
    public function groupOfShift(?Shift $shift): ?string
    {
        if (! $shift || ! $shift->start_time) {
            return null;
        }

        $start = $shift->start_time->format('H:i');

        foreach (self::GROUPS as $group => $definition) {
            $first = $definition['slots'][0][1];
            $last = end($definition['slots'])[2];
            if ($start >= $first && $start < $last) {
                return $group;
            }
        }

        return null;
    }

    /** Duración en minutos de la hora pedagógica del turno (45 mañana, 40 noche, 60 si no aplica). */
    public function minutesPerHour(?Shift $shift): int
    {
        $group = $this->groupOfShift($shift);

        return $group ? self::GROUPS[$group]['minutes'] : self::DEFAULT_MINUTES;
    }

    /**
     * Franjas que toca un bloque (solapamiento estricto). $start y $end en H:i o H:i:s.
     *
     * @return array<int, array{group: string, index: int, label: string, start: string, end: string}>
     */
    public function slotsTouching(string $start, string $end): array
    {
        $start = substr(TimeCast::normalize($start), 0, 5);
        $end = substr(TimeCast::normalize($end), 0, 5);

        return array_values(array_filter(
            $this->slots(),
            fn (array $slot) => $start < $slot['end'] && $end > $slot['start']
        ));
    }

    /** true si el bloque empieza y termina exactamente en límites de franjas de una misma jornada. */
    public function fitsSlots(string $start, string $end): bool
    {
        $start = substr(TimeCast::normalize($start), 0, 5);
        $end = substr(TimeCast::normalize($end), 0, 5);
        $touched = $this->slotsTouching($start, $end);

        return $touched !== []
            && $touched[0]['start'] === $start
            && end($touched)['end'] === $end
            && count(array_unique(array_column($touched, 'group'))) === 1;
    }
}
