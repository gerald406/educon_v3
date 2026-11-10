<?php

namespace App\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * [NUEVO] Cast para columnas TIME (horarios y turnos).
 *
 * - Al leer: devuelve Carbon (fecha de hoy + hora), para que funcione
 *   $schedule->start_time->format('H:i') / diffInMinutes() en vistas, reportes y exportaciones.
 * - Al escribir: acepta 'H:i', 'H:i:s' o un DateTime y guarda siempre 'H:i:s',
 *   de modo que las comparaciones en SQL (solapamientos) sean consistentes.
 *
 * Laravel no tiene un cast nativo 'time': con 'time' o 'string' el valor llegaba como
 * texto y ->format() fallaba ("Call to a member function format() on string").
 */
class TimeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::createFromFormat('H:i:s', self::normalize($value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::normalize($value);
    }

    /**
     * Normaliza a 'H:i:s'. También lo usan las consultas de cruces de horario.
     */
    public static function normalize(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $value = trim((string) $value);

        // 'Y-m-d H:i:s' (p. ej. valores guardados con el cast datetime anterior)
        if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3] ?? 0);
        }

        throw new InvalidArgumentException("Hora no válida: {$value}");
    }
}
