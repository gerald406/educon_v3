{{-- [NUEVO] Programación de Actividades Académicas del Docente (réplica del formato oficial en Word).
     Las actividades y subactividades son las que el docente configuró en su horario no lectivo. --}}
@php
    $h = fn ($hours) => rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.');
    $mark = fn (string $type) => $data['contract_type'] === $type ? 'X' : '&nbsp;&nbsp;';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Programación de Actividades Académicas del Docente</title>
    <style>
        @page { margin: 1cm 1.8cm 1.2cm 1.8cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #000; }
        .header-image { width: 100%; margin-bottom: 4px; }
        h1 { font-size: 12pt; text-align: center; margin: 0; }
        .subtitle { text-align: center; font-weight: bold; font-size: 10pt; margin: 2px 0; }
        .norms { text-align: center; font-size: 7.5pt; margin: 0 0 10px 0; }
        .section { font-weight: bold; margin: 7px 0 3px 0; }
        table { width: 100%; border-collapse: collapse; }
        .general td { padding: 2px 0; }
        .grid th, .grid td { border: 1px solid #000; padding: 2px 5px; vertical-align: middle; }
        .grid th { background: #d9d9d9; font-size: 8pt; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .notes { font-size: 7pt; text-align: justify; margin-top: 6px; }
        .notes p { margin: 2px 0; }
        /* Fecha y firmas siempre juntas, aunque pasen a otra hoja */
        .closing { page-break-inside: avoid; }
        .date { text-align: right; margin-top: 10px; }
        .signatures { margin-top: 55px; }
        .signatures td { width: 33%; text-align: center; font-size: 8.5pt; padding: 0 10px; }
        .signatures .line { border-top: 1px solid #000; padding-top: 3px; }
    </style>
</head>
<body>
    @if ($headerImage)
        <img src="{{ $headerImage }}" class="header-image" alt="">
    @endif

    <h1>PROGRAMACIÓN DE ACTIVIDADES ACADÉMICAS DEL DOCENTE</h1>
    <p class="subtitle">{{ $semesterName }} {{ $data['period_code'] }}</p>
    <p class="norms">( R.V.M. N°085-2025-MINEDU, R.V.M. N°049-2022-MINEDU y R.S.G. N°349-2017-MINEDU )</p>

    <p class="section">DATOS GENERALES:</p>
    <table class="general">
        <tr>
            <td style="width: 70%;"><span class="bold">APELLIDOS Y NOMBRES:</span> {{ $data['teacher_name'] }}</td>
            <td><span class="bold">HORAS SEMANALES:</span> {{ $data['workday_hours'] }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="bold">CONDICIÓN:</span> NOMBRADO ( {!! $mark('permanent') !!} ) &nbsp; CONTRATADO ( {!! $mark('contracted') !!} )</td>
        </tr>
        <tr>
            <td><span class="bold">PROGRAMA DE ESTUDIOS:</span> {{ $data['program'] }}</td>
            <td><span class="bold">AÑO ACADÉMICO:</span> {{ $data['period_code'] }}</td>
        </tr>
    </table>

    <p class="section">DISTRIBUCIÓN DE CARGA HORARIA LECTIVA Y NO LECTIVA:</p>

    <table class="grid">
        <thead>
            <tr><th colspan="5">CARGA LECTIVA</th></tr>
            <tr>
                <th>Unidades Didácticas</th>
                <th>Programa de Estudios</th>
                <th style="width: 9%;">Sem.</th>
                <th style="width: 10%;">Sección</th>
                <th style="width: 9%;">Horas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['lective_rows'] as $row)
                <tr>
                    <td>{{ $row['unit'] }}</td>
                    <td>{{ $row['program'] }}</td>
                    <td class="center">{{ \Illuminate\Support\Str::before($row['semester_shift'], '-') }}</td>
                    <td class="center">{{ mb_strtoupper($row['section']) }}</td>
                    <td class="center">{{ $row['hours'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="right bold">SUB TOTAL CARGA LECTIVA</td>
                <td class="center bold">{{ $data['lective_hours'] }}</td>
            </tr>
        </tbody>
    </table>

    <table class="grid" style="margin-top: 8px;">
        <thead>
            <tr><th colspan="3">CARGA NO LECTIVA</th></tr>
            <tr>
                <th style="width: 34%;">Actividad</th>
                <th>Subactividad</th>
                <th style="width: 9%;">Horas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['activities'] as $activity)
                <tr>
                    <td>{{ $activity['letter'] }}) {{ $activity['label'] }}.</td>
                    <td>{{ $activity['sub_activity'] }}</td>
                    <td class="center">{{ $h($activity['hours']) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" class="right bold">SUB TOTAL CARGA NO LECTIVA</td>
                <td class="center bold">{{ $h($data['non_teaching_hours']) }}</td>
            </tr>
            <tr>
                <td colspan="2" class="right bold">TOTAL HORAS</td>
                <td class="center bold">{{ $h($data['total_hours']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="notes">
        <p>Las actividades no lectivas se realizan dentro de la jornada laboral del docente y, dentro o fuera del Instituto de Educación Superior (6.4-RSG N° 349-2017-MINEDU).</p>
        <p>Los docentes que asuman carga no lectiva deben presentar al Jefe de Área Académica, un informe final de cumplimiento de los productos o resultados institucionales y/o académicos al finalizar el año o al concluir sus actividades (9.9, Literal b) RSG 349-2017-MINEDU).</p>
        <p>Constituyen faltas leves: g) Incumplir con las labores asignadas en las horas no lectivas, o cumplirlas en forma deficiente (Art. 81 Ley N° 30512).</p>
    </div>

    <div class="closing">
        <p class="date">Puno, {{ $dateText }}</p>

        <table class="signatures">
            <tr>
                <td style="vertical-align: top;"><div class="line">Docente</div></td>
                <td style="vertical-align: top;"><div class="line">Coordinador del Programa de Estudios</div></td>
                <td style="vertical-align: top;"><div class="line">Jefe de Unidad Académica</div></td>
            </tr>
        </table>
    </div>
</body>
</html>
