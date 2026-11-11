<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi Horario (Periodo {{ $period?->code ?? 'N/A' }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (! $teacher)
                <div class="bg-white shadow-xl sm:rounded-lg p-6 text-gray-700">
                    Su usuario no tiene una ficha de docente, por lo que no tiene un horario personal.
                    Si dicta clases, solicite al área académica que registre su ficha.
                </div>
            @elseif (! $period)
                <div class="bg-white shadow-xl sm:rounded-lg p-6 text-gray-700">
                    No hay un período académico activo. El horario estará disponible cuando se active el período.
                </div>
            @elseif (empty($schedule['rows']))
                <div class="bg-white shadow-xl sm:rounded-lg p-6 text-gray-700">
                    Todavía no tiene unidades didácticas asignadas en el período {{ $period->code }}.
                    Su horario aparecerá aquí cuando el área académica registre su carga.
                </div>
            @else
                {{-- Resumen y descarga --}}
                <div class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Docente</p>
                        <p class="font-bold text-gray-900">{{ $schedule['teacher_name'] }}</p>
                        <p class="text-sm text-gray-600 mt-1">
                            Total carga lectiva: <span class="font-bold text-gray-900">{{ $schedule['total_hours'] }} h</span> semanales
                            · {{ count($schedule['rows']) }} {{ count($schedule['rows']) === 1 ? 'sección' : 'secciones' }}
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <a href="{{ route('teacher.non-teaching-schedule') }}"
                            class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-bold rounded shadow-sm">
                            Horario no lectivo
                        </a>
                        <a href="{{ route('teacher.my-schedule.personal-excel') }}"
                            class="inline-flex items-center justify-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded shadow">
                            Descargar horario personal (Excel)
                        </a>
                    </div>
                </div>

                {{-- Avisos: no impiden la descarga --}}
                @if (! empty($schedule['warnings']))
                    <div class="bg-amber-50 border border-amber-200 text-amber-900 sm:rounded-lg p-4 text-sm">
                        <p class="font-bold mb-1">Revise antes de presentar su horario</p>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($schedule['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs">
                            Puede descargar el documento de todos modos. Estos datos los registra el área académica
                            (carga académica y gestión de horarios): si algo no es correcto, comuníquelo a su coordinador.
                        </p>
                    </div>
                @endif

                {{-- Grilla semanal por franjas oficiales --}}
                <div class="bg-white shadow-xl sm:rounded-lg p-6">
                    <h3 class="font-bold text-gray-800 mb-3">Horario semanal</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-[900px] w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th class="border border-gray-300 bg-indigo-900 text-white p-2 w-28">Hora</th>
                                    @foreach ($dayLabels as $label)
                                        <th class="border border-gray-300 bg-indigo-900 text-white p-2 uppercase">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($groups as $groupKey => $group)
                                    <tr>
                                        <td colspan="{{ count($dayLabels) + 1 }}" class="border border-gray-300 bg-gray-200 text-gray-700 font-semibold px-2 py-1">
                                            {{ $group['label'] }} · horas de {{ $group['minutes'] }} min
                                        </td>
                                    </tr>
                                    @foreach ($group['slots'] as $index => [$slotLabel, $slotStart, $slotEnd])
                                        <tr wire:key="slot-{{ $groupKey }}-{{ $index }}">
                                            <td class="border border-gray-300 bg-gray-50 text-center p-2 whitespace-nowrap">
                                                <span class="font-bold block">{{ $slotLabel }}</span>
                                                {{ $slotStart }} - {{ $slotEnd }}
                                            </td>
                                            @foreach ($dayLabels as $dayKey => $label)
                                                @php $texts = $schedule['grid'][$groupKey][$index][$dayKey] ?? []; @endphp
                                                <td class="border border-gray-300 p-2 align-middle text-center {{ $texts ? 'bg-blue-50 text-blue-900' : '' }}">
                                                    {{ implode(' / ', $texts) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Carga lectiva --}}
                <div class="bg-white shadow-xl sm:rounded-lg p-6">
                    <h3 class="font-bold text-gray-800 mb-3">Carga lectiva</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Programa de estudios</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unidad didáctica</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sección</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Semestre y turno</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Teoría</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Práctica</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Horas semanales</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($schedule['rows'] as $row)
                                    <tr wire:key="row-{{ $row['assignment_id'] }}">
                                        <td class="px-4 py-2">{{ $row['program'] }}</td>
                                        <td class="px-4 py-2 font-semibold text-gray-900">{{ $row['unit'] }}</td>
                                        <td class="px-4 py-2">{{ $row['section'] }}</td>
                                        <td class="px-4 py-2">{{ $row['semester_shift'] }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['theory'] ?? '—' }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['practice'] ?? '—' }}</td>
                                        <td class="px-4 py-2 text-center font-bold">{{ $row['hours'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50">
                                    <td colspan="6" class="px-4 py-2 text-right font-bold">TOTAL CARGA LECTIVA</td>
                                    <td class="px-4 py-2 text-center font-bold">{{ $schedule['total_hours'] }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
