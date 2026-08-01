<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; }
        .report-header { margin-bottom: 20px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        .report-title { font-size: 18px; font-weight: bold; color: #1e1b4b; margin: 0; }
        .info-grid { margin-top: 5px; }
        .info-item { margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: center; word-wrap: break-word; }
        .day-header { background: #4f46e5; color: white; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        .time-col { background: #f9fafb; font-weight: bold; width: 60px; }
        .course-box { margin-bottom: 4px; padding: 4px; background: #f3f4f6; border-radius: 3px; }
        .teacher-name { color: #4b5563; font-style: italic; font-size: 9px; }
        .classroom-name { font-weight: bold; color: #1f2937; font-size: 9px; }
    </style>
</head>
<body>
    <div class="report-header">
        <div class="report-title">HORARIO CONSOLIDADO SEMESTRAL</div>
        <div class="info-grid">
            <div class="info-item"><strong>Programa de Estudios:</strong> {{ $career->name ?? 'No especificado' }}</div>
            <div class="info-item">
                <strong>Semestre:</strong> {{ $cycle }} &nbsp;&nbsp; | &nbsp;&nbsp; 
                <strong>Turno:</strong> {{ $shift->name ?? 'No especificado' }} &nbsp;&nbsp; | &nbsp;&nbsp; 
                <strong>Periodo:</strong> {{ $period->name ?? 'N/A' }}
            </div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="day-header" style="width: 70px;">Hora</th>
                @foreach(['monday' => 'Lunes', 'tuesday' => 'Martes', 'wednesday' => 'Miércoles', 'thursday' => 'Jueves', 'friday' => 'Viernes', 'saturday' => 'Sábado'] as $label)
                    <th class="day-header">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($timeSlots as $slot)
                <tr>
                    <td class="time-col">{{ $slot['start'] }} - {{ $slot['end'] }}</td>
                    @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day)
                        <td>
                            @php
                                $cellSchedules = $schedules->get($day, collect())->filter(function($schedule) use ($slot) {
                                    // Verificar solapamiento: inicio del curso < fin del slot Y fin del curso > inicio del slot
                                    $courseStart = $schedule->start_time->format('H:i');
                                    $courseEnd   = $schedule->end_time->format('H:i');
                                    return $courseStart < $slot['end'] && $courseEnd > $slot['start'];
                                });
                            @endphp
                            @foreach($cellSchedules as $s)
                                <div class="course-box">
                                    <strong>{{ $s->teacherAssignment->didacticUnit->name }}</strong><br>
                                    <span class="teacher-name">{{ $s->teacherAssignment->teacher->user->lastname }}</span><br>
                                    <span class="classroom-name">[{{ $s->classroomResource->name ?? 'S/A' }}]</span>
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>