<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Horario No Lectivo (Periodo {{ $period?->code ?? 'N/A' }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (! $teacher)
                <div class="bg-white shadow-xl sm:rounded-lg p-6 text-gray-700">
                    Su usuario no tiene una ficha de docente, por lo que no puede registrar un horario no lectivo.
                    Si dicta clases, solicite al área académica que registre su ficha.
                </div>
            @elseif (! $period)
                <div class="bg-white shadow-xl sm:rounded-lg p-6 text-gray-700">
                    No hay un período académico activo. Podrá registrar su horario no lectivo cuando se active el período.
                </div>
            @elseif ($summary['lective_hours'] === 0)
                <div class="bg-white shadow-xl sm:rounded-lg p-6 text-gray-700">
                    Todavía no tiene carga lectiva en el período {{ $period->code }}. El horario no lectivo se registra
                    cuando el área académica le asigne sus unidades didácticas.
                </div>
            @else
                @php
                    $h = fn ($minutes) => $service->formatHours((int) $minutes);
                    // Paleta por actividad (estilos en línea: no dependen del build de Tailwind)
                    $palette = [
                        ['#eff6ff', '#3b82f6', '#1e3a8a'], ['#ecfdf5', '#10b981', '#064e3b'],
                        ['#fff7ed', '#f97316', '#7c2d12'], ['#f5f3ff', '#8b5cf6', '#4c1d95'],
                    ];
                    $selected = collect($summary['activities'])->firstWhere('id', (int) $selectedActivityId);
                    $difference = $summary['difference_minutes'];

                    // Estado de cada media hora: clase, bloque no lectivo o libre
                    $halves = [];
                    foreach (array_keys($dayLabels) as $dayKey) {
                        for ($m = \App\Services\NonTeachingScheduleService::GRID_START; $m < \App\Services\NonTeachingScheduleService::GRID_END; $m += 30) {
                            $halves[$dayKey][$m] = ['type' => 'free'];
                            foreach ($summary['lective'][$dayKey] ?? [] as $class) {
                                if ($m < $class['end'] && $m + 30 > $class['start']) {
                                    $halves[$dayKey][$m] = ['type' => 'lective', 'label' => $class['label']];
                                }
                            }
                        }
                    }
                    foreach ($summary['blocks'] as $block) {
                        for ($m = $block['start']; $m < $block['end']; $m += 30) {
                            $halves[$block['day']][$m] = ['type' => 'block'] + $block;
                        }
                    }
                @endphp

                {{-- Resumen de la jornada --}}
                <div class="bg-white shadow-xl sm:rounded-lg p-6">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500">Condición</p>
                            <p class="font-bold text-gray-900">{{ $summary['workday_label'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500">Horas lectivas</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $summary['lective_hours'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500">Horas no lectivas</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $h($summary['non_teaching_minutes']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500">Total</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $h($summary['total_minutes']) }} / {{ $summary['workday_hours'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500">Estado</p>
                            @if ($summary['is_complete'])
                                <p class="font-bold text-green-700">Completo</p>
                            @elseif ($difference > 0)
                                <p class="font-bold text-amber-700">Faltan {{ $h($difference) }} h</p>
                            @elseif ($difference < 0)
                                <p class="font-bold text-red-700">Sobran {{ $h(-$difference) }} h</p>
                            @else
                                <p class="font-bold text-amber-700">Por revisar</p>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-4">
                        Las horas lectivas provienen de su carga académica. La suma de horas lectivas y no lectivas debe ser
                        exactamente {{ $summary['workday_hours'] }} horas semanales.
                        <a href="{{ route('teacher.my-schedule') }}" class="text-indigo-600 hover:underline">Ver mi horario lectivo</a>
                    </p>
                </div>

                {{-- [NUEVO] Documentos oficiales: solo con el horario completo --}}
                <div class="bg-white shadow-xl sm:rounded-lg p-6">
                    <h3 class="font-bold text-gray-800 mb-1">Documentos oficiales</h3>
                    @if (session('document_error'))
                        <p class="text-sm text-red-700 mb-2">{{ session('document_error') }}</p>
                    @endif
                    @if ($summary['is_complete'])
                        <p class="text-sm text-gray-600 mb-3">Su horario no lectivo está completo. Descargue, imprima y firme sus documentos.</p>
                        @unless ($summary['has_program'])
                            <p class="text-sm text-amber-700 mb-3">
                                Su ficha no tiene programa de estudios de adscripción: ese dato saldrá en blanco. Solicite al área académica que lo registre.
                            </p>
                        @endunless
                        <div class="flex flex-col sm:flex-row gap-2">
                            <a href="{{ route('teacher.non-teaching-schedule.excel') }}"
                                class="inline-flex items-center justify-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded shadow">
                                Horario no lectivo (Excel)
                            </a>
                            <a href="{{ route('teacher.non-teaching-schedule.activity-program-pdf') }}"
                                class="inline-flex items-center justify-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded shadow">
                                Programación de actividades académicas (PDF)
                            </a>
                        </div>
                    @else
                        <p class="text-sm text-gray-600">
                            Podrá descargar el Horario no lectivo (Excel) y la Programación de actividades académicas (PDF)
                            cuando complete los pendientes que se indican abajo.
                        </p>
                    @endif

                    @if ($canCopy)
                        <div class="mt-4 pt-4 border-t">
                            <button type="button" wire:click="copyFromPrevious" wire:loading.attr="disabled" wire:target="copyFromPrevious"
                                wire:confirm="Se copiarán las actividades y horas ubicadas de su período anterior. ¿Continuar?"
                                class="text-sm bg-white border border-gray-300 rounded px-3 py-2 hover:bg-gray-50 disabled:opacity-50">
                                Copiar del período anterior
                            </button>
                            <span class="text-xs text-gray-500 ml-2">Disponible mientras no registre actividades en este período.</span>
                        </div>
                    @endif
                </div>

                {{-- Pendientes --}}
                @if (! empty($summary['issues']))
                    <div class="bg-amber-50 border border-amber-200 text-amber-900 sm:rounded-lg p-4 text-sm">
                        <p class="font-bold mb-1">Pendiente para completar su horario no lectivo</p>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($summary['issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- 1. Actividades --}}
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white shadow-xl sm:rounded-lg p-4">
                            <h3 class="font-bold text-gray-800 mb-1">1. Actividades no lectivas</h3>
                            <p class="text-xs text-gray-500 mb-3">
                                Máximo {{ \App\Services\NonTeachingScheduleService::MAX_ACTIVITIES }} actividades, incluida Preparación de clases (obligatoria).
                            </p>

                            <div class="space-y-2">
                                @foreach ($summary['activities'] as $a)
                                    @php [$bg, $border, $text] = $palette[$a['index'] % count($palette)]; @endphp
                                    <div wire:key="activity-{{ $a['id'] }}"
                                        class="p-3 rounded border text-sm {{ (int) $selectedActivityId === $a['id'] ? 'ring-2 ring-indigo-500' : '' }}"
                                        style="background: {{ $bg }}; border-left: 4px solid {{ $border }}; color: {{ $text }};">
                                        <div class="flex justify-between gap-2">
                                            <span class="font-semibold">{{ $a['label'] }}</span>
                                            <span class="font-bold whitespace-nowrap">{{ $h($a['minutes']) }} h</span>
                                        </div>
                                        <p class="text-xs mt-1 break-words">
                                            {{ $a['sub_activity'] !== '' ? $a['sub_activity'] : 'Falta describir la subactividad.' }}
                                        </p>
                                        <p class="text-xs mt-1">
                                            Ubicadas en la semana: {{ $h($a['placed_minutes']) }} / {{ $h($a['minutes']) }} h
                                            @if ($a['is_preparation'])· calculada según el Anexo 2 @endif
                                        </p>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <button type="button" wire:click="selectActivity({{ $a['id'] }})"
                                                class="text-xs bg-white border border-gray-300 rounded px-2 py-1 hover:bg-gray-50">
                                                Ubicar en la semana
                                            </button>
                                            <button type="button" wire:click="editActivity({{ $a['id'] }})"
                                                class="text-xs bg-white border border-gray-300 rounded px-2 py-1 hover:bg-gray-50">
                                                {{ $a['is_preparation'] ? 'Describir' : 'Editar' }}
                                            </button>
                                            @unless ($a['is_preparation'])
                                                <button type="button" wire:click="confirmRemoveActivity({{ $a['id'] }})"
                                                    class="text-xs bg-white border border-red-300 text-red-700 rounded px-2 py-1 hover:bg-red-50">
                                                    Quitar
                                                </button>
                                            @endunless
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if (! $showForm)
                                @if ($summary['can_add_activity'])
                                    <button type="button" wire:click="newActivity"
                                        class="mt-3 w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">
                                        Agregar actividad
                                    </button>
                                @else
                                    <p class="mt-3 text-xs text-gray-500">Ya registró el máximo de actividades.</p>
                                @endif
                            @endif
                        </div>

                        {{-- Formulario --}}
                        @if ($showForm)
                            @php $editing = collect($summary['activities'])->firstWhere('id', (int) $editingActivityId); @endphp
                            <div class="bg-white shadow-xl sm:rounded-lg p-4 border-t-4 {{ $editing ? 'border-amber-500' : 'border-blue-500' }}">
                                <h3 class="font-bold text-gray-800 mb-3">
                                    {{ $editing ? 'Editar: ' . $editing['label'] : 'Nueva actividad' }}
                                </h3>
                                <div class="space-y-3">
                                    @if (! $editing)
                                        <div>
                                            <x-label value="Actividad" />
                                            <select wire:model="activity" class="w-full border-gray-300 rounded-md text-sm">
                                                <option value="">-- Seleccione --</option>
                                                @foreach ($summary['available_activities'] as $key => $label)
                                                    @continue($key === \App\Models\NonTeachingActivity::PREPARATION)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif

                                    <div>
                                        <x-label value="Subactividad (qué realizará)" />
                                        <textarea wire:model="sub_activity" rows="3" maxlength="255"
                                            class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                            placeholder="Describa la actividad específica (5 a 255 caracteres)"></textarea>
                                    </div>

                                    @if ($editing && $editing['is_preparation'])
                                        <p class="text-xs text-gray-600">
                                            Horas semanales: <span class="font-bold">{{ $h($editing['minutes']) }}</span>
                                            (se calculan con el Anexo 2 según sus {{ $summary['lective_hours'] }} horas lectivas y no se pueden modificar).
                                        </p>
                                    @else
                                        <div>
                                            <x-label value="Horas semanales" />
                                            <x-input type="number" wire:model="hours" min="1" step="0.5" class="w-full" placeholder="Ej. 2 o 2.5" />
                                            <p class="text-xs text-gray-500 mt-1">Horas completas o medias horas, mínimo 1.</p>
                                        </div>
                                    @endif

                                    <div class="flex gap-2">
                                        <button type="button" wire:click="saveActivity" wire:loading.attr="disabled" wire:target="saveActivity"
                                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow disabled:opacity-50">
                                            Guardar
                                        </button>
                                        <button type="button" wire:click="cancelForm"
                                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- 2. Semana --}}
                    <div class="lg:col-span-2 bg-white shadow-xl sm:rounded-lg p-4">
                        <h3 class="font-bold text-gray-800 mb-1">2. Distribución en la semana</h3>
                        <p class="text-xs text-gray-600 mb-3">
                            @if ($selected)
                                Ubicando: <span class="font-bold">{{ $selected['label'] }}</span>
                                ({{ $h($selected['placed_minutes']) }} / {{ $h($selected['minutes']) }} h).
                                Pulse una casilla libre para ocupar esa hora; pulse un bloque para quitarlo.
                            @else
                                Pulse "Ubicar en la semana" en una actividad y luego elija las casillas libres. Pulse un bloque para quitarlo.
                            @endif
                            Las casillas grises son sus horas de clase.
                        </p>

                        <div class="overflow-x-auto">
                            <table class="min-w-[720px] w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-300 bg-indigo-900 text-white p-2 w-24">Hora</th>
                                        @foreach ($dayLabels as $label)
                                            <th class="border border-gray-300 bg-indigo-900 text-white p-2 uppercase">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @for ($hour = \App\Services\NonTeachingScheduleService::GRID_START; $hour < \App\Services\NonTeachingScheduleService::GRID_END; $hour += 60)
                                        <tr wire:key="hour-{{ $hour }}">
                                            <td class="border border-gray-300 bg-gray-50 text-center p-1 whitespace-nowrap">
                                                {{ $service->toTime($hour) }} - {{ $service->toTime($hour + 60) }}
                                            </td>
                                            @foreach ($dayLabels as $dayKey => $label)
                                                @php
                                                    $first = $halves[$dayKey][$hour];
                                                    $second = $halves[$dayKey][$hour + 30];
                                                    $whole = $first['type'] !== 'free' && $first['type'] === $second['type']
                                                        && (($first['type'] === 'block' && $first['id'] === $second['id'])
                                                            || ($first['type'] === 'lective' && $first['label'] === $second['label']));
                                                    $parts = $whole ? [[$hour, $first, 'h-14']] : [[$hour, $first, 'h-7'], [$hour + 30, $second, 'h-7']];
                                                @endphp
                                                <td class="border border-gray-300 p-0 align-top" wire:key="cell-{{ $dayKey }}-{{ $hour }}">
                                                    @foreach ($parts as [$minute, $cell, $height])
                                                        @if ($cell['type'] === 'lective')
                                                            <div class="{{ $height }} px-1 flex items-center justify-center text-center bg-gray-200 text-gray-600 overflow-hidden leading-tight"
                                                                title="Clase: {{ $cell['label'] }}">
                                                                <span class="truncate">Clase</span>
                                                            </div>
                                                        @elseif ($cell['type'] === 'block')
                                                            @continue($minute !== $cell['start'] && ! ($whole))
                                                            @php [$bg, $border, $text] = $cell['conflict'] ? ['#fee2e2', '#dc2626', '#7f1d1d'] : $palette[$cell['index'] % count($palette)]; @endphp
                                                            <button type="button" wire:click="removeBlock({{ $cell['id'] }})"
                                                                title="{{ $cell['label'] }} ({{ $service->toTime($cell['start']) }} - {{ $service->toTime($cell['end']) }}). Pulse para quitar."
                                                                class="{{ $height }} w-full px-1 flex items-center justify-center text-center overflow-hidden leading-tight font-semibold hover:opacity-75"
                                                                style="background: {{ $bg }}; border-left: 4px solid {{ $border }}; color: {{ $text }};">
                                                                <span class="truncate">@if ($cell['conflict'])⚠ @endif{{ $cell['label'] }}</span>
                                                            </button>
                                                        @else
                                                            <button type="button" wire:click="placeBlock('{{ $dayKey }}', '{{ $service->toTime($minute) }}')"
                                                                class="{{ $height }} w-full block hover:bg-indigo-50 {{ $loop->first && ! $whole ? 'border-b border-dashed border-gray-200' : '' }}"
                                                                title="{{ $label }} {{ $service->toTime($minute) }}: libre"></button>
                                                        @endif
                                                    @endforeach
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
