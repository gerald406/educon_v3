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
                                @if ($summary['blocked_by_load'])
                                    <p class="text-xs text-red-700">Carga lectiva por revisar</p>
                                @endif
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

                {{-- [MODIFICADO] Documentos oficiales: los botones siempre están a la vista y, si aún no se
                     pueden usar, la lista de requisitos dice exactamente qué falta y quién lo resuelve --}}
                @php
                    $checks = $summary['checks'];
                    $done = collect($checks)->where('ok', true)->count();
                    $progress = (int) round($done / max(1, count($checks)) * 100);
                    $ready = $summary['is_complete'];
                @endphp
                <div class="bg-white shadow-xl sm:rounded-lg p-6 border-t-4 {{ $ready ? 'border-green-500' : ($summary['blocked_by_load'] ? 'border-red-500' : 'border-amber-400') }}">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="md:max-w-xl">
                            <h3 class="font-bold text-gray-800">Documentos oficiales</h3>
                            @if ($ready)
                                <p class="text-sm text-green-700 font-semibold mt-1">Su horario no lectivo está completo: ya puede descargar, imprimir y firmar sus documentos.</p>
                            @elseif ($summary['blocked_by_load'])
                                <p class="text-sm text-red-700 font-semibold mt-1">Todavía no puede descargar sus documentos, y la causa no depende de usted.</p>
                            @else
                                <p class="text-sm text-gray-600 mt-1">
                                    Podrá descargarlos cuando complete los {{ count($checks) - $done }} requisito(s) pendiente(s) de la lista.
                                </p>
                            @endif
                            @if (session('document_error'))
                                <p class="text-sm text-red-700 mt-1">{{ session('document_error') }}</p>
                            @endif
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                            @if ($ready)
                                <a href="{{ route('teacher.non-teaching-schedule.excel') }}"
                                    class="inline-flex items-center justify-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded shadow">
                                    Horario no lectivo (Excel)
                                </a>
                                <a href="{{ route('teacher.non-teaching-schedule.activity-program-pdf') }}"
                                    class="inline-flex items-center justify-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded shadow">
                                    Programación de actividades (PDF)
                                </a>
                            @else
                                <span class="inline-flex items-center justify-center px-4 py-2 bg-gray-200 text-gray-500 font-bold rounded cursor-not-allowed"
                                    title="Disponible cuando complete los requisitos" aria-disabled="true">
                                    Horario no lectivo (Excel)
                                </span>
                                <span class="inline-flex items-center justify-center px-4 py-2 bg-gray-200 text-gray-500 font-bold rounded cursor-not-allowed"
                                    title="Disponible cuando complete los requisitos" aria-disabled="true">
                                    Programación de actividades (PDF)
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Avance --}}
                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Requisitos cumplidos: {{ $done }} de {{ count($checks) }}</span>
                            <span>{{ $progress }}%</span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded">
                            <div class="h-2 rounded" style="width: {{ $progress }}%; background: {{ $ready ? '#16a34a' : ($summary['blocked_by_load'] ? '#dc2626' : '#f59e0b') }};"></div>
                        </div>
                    </div>

                    {{-- Requisitos --}}
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($checks as $key => $check)
                            <li wire:key="check-{{ $key }}" class="flex gap-2">
                                @if ($check['ok'])
                                    <span class="font-bold text-green-600" aria-hidden="true">✓</span>
                                    <span class="text-gray-700">{{ $check['label'] }}</span>
                                @else
                                    <span class="font-bold {{ $check['who'] === 'office' ? 'text-red-600' : 'text-amber-600' }}" aria-hidden="true">✗</span>
                                    <div>
                                        <span class="font-semibold text-gray-900">{{ $check['label'] }}</span>
                                        @if ($check['who'] === 'office')
                                            <span class="ml-1 text-xs font-semibold text-red-700 bg-red-100 rounded px-1.5 py-0.5">Lo resuelve el área académica</span>
                                        @endif
                                        <ul class="mt-1 space-y-1 text-gray-600">
                                            @foreach ($check['details'] as $detail)
                                                <li>{{ $detail }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if ($ready && ! $summary['has_program'])
                        <p class="text-sm text-amber-700 mt-3">
                            Su ficha no tiene programa de estudios de adscripción: ese dato saldrá en blanco en los documentos. Solicite al área académica que lo registre.
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
                                            @if ($a['minutes'] > 0 && $a['placed_minutes'] === $a['minutes'])
                                                <span class="font-semibold">✓ Horas ubicadas: {{ $h($a['placed_minutes']) }} de {{ $h($a['minutes']) }}</span>
                                            @else
                                                Horas ubicadas: {{ $h($a['placed_minutes']) }} de {{ $h($a['minutes']) }}
                                                @if ($a['placed_minutes'] < $a['minutes'])· <span class="font-semibold">faltan {{ $h($a['minutes'] - $a['placed_minutes']) }}</span>@endif
                                            @endif
                                            @if ($a['is_preparation'])· calculada según el Anexo 2 @endif
                                        </p>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            @if ((int) $selectedActivityId === $a['id'])
                                                <span class="text-xs bg-indigo-600 text-white font-semibold rounded px-2 py-1">Ubicando en la semana →</span>
                                            @else
                                                <button type="button" wire:click="selectActivity({{ $a['id'] }})"
                                                    class="text-xs bg-white border border-gray-300 rounded px-2 py-1 hover:bg-gray-50">
                                                    Ubicar en la semana
                                                </button>
                                            @endif
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
                        @if ($selected)
                            @php
                                [$selBg, $selBorder, $selText] = $palette[$selected['index'] % count($palette)];
                                $selPending = $selected['minutes'] - $selected['placed_minutes'];
                            @endphp
                            <div class="text-sm rounded p-2 mb-2" style="background: {{ $selBg }}; border-left: 4px solid {{ $selBorder }}; color: {{ $selText }};">
                                Ubicando <span class="font-bold">{{ $selected['label'] }}</span>:
                                @if ($selPending > 0)
                                    faltan <span class="font-bold">{{ $h($selPending) }} h</span>. Pulse una casilla libre para ocupar esa hora.
                                @elseif ($selPending < 0)
                                    sobran <span class="font-bold">{{ $h(-$selPending) }} h</span>. Elimine uno de sus bloques con el icono rojo.
                                @else
                                    todas sus horas están ubicadas. Para mover un bloque, elimínelo con el icono rojo y ubíquelo en otra casilla.
                                @endif
                            </div>
                        @else
                            <p class="text-sm text-gray-600 mb-2">Pulse «Ubicar en la semana» en una actividad y luego elija las casillas libres.</p>
                        @endif

                        {{-- [NUEVO] Icono rojo para eliminar un bloque (CSS propio: no depende del build de Tailwind) --}}
                        <style>
                            .educon-block-delete {
                                flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center;
                                height: 20px; width: 20px; border-radius: 9999px;
                                background: #fff; color: #dc2626; border: 1px solid #fca5a5;
                            }
                            .educon-block-delete:hover { background: #dc2626; color: #fff; border-color: #dc2626; }
                            .educon-block-delete:focus-visible { outline: 2px solid #dc2626; outline-offset: 1px; }
                            .educon-block-delete[disabled] { opacity: .5; cursor: wait; }
                        </style>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 mb-3">
                            <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 border border-gray-300 bg-white"></span> Libre</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 bg-gray-200"></span> Clase (no disponible)</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3" style="background: #eff6ff; border-left: 3px solid #3b82f6;"></span> Actividad no lectiva</span>
                            <span class="inline-flex items-center gap-1"><span class="educon-block-delete" style="height: 16px; width: 16px;"><svg style="height: 10px; width: 10px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></span> Eliminar bloque</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3" style="background: #fee2e2; border-left: 3px solid #dc2626;"></span> Cruce con una clase</span>
                            <span wire:loading wire:target="placeBlock, removeBlock, selectActivity" class="font-semibold text-indigo-600">Guardando…</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-xs" style="min-width: 560px; table-layout: fixed;">
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
                                                            {{-- [MODIFICADO] El bloque ya no es un botón: se elimina con el icono rojo --}}
                                                            @php $blockTime = $service->toTime($cell['start']).' - '.$service->toTime($cell['end']); @endphp
                                                            <div class="{{ $height }} w-full flex items-center overflow-hidden leading-tight font-semibold"
                                                                title="{{ $cell['label'] }} ({{ $blockTime }})"
                                                                style="background: {{ $bg }}; border-left: 4px solid {{ $border }}; color: {{ $text }}; padding: 0 3px 0 4px; gap: 3px;">
                                                                {{-- En el bloque de una hora el nombre puede ocupar varias líneas; en el de media hora, una --}}
                                                                <span class="{{ $whole ? '' : 'truncate' }} text-center"
                                                                    style="flex: 1 1 0%; min-width: 0; max-height: 100%; overflow: hidden; {{ $whole ? 'white-space: normal; overflow-wrap: anywhere; line-height: 1.15;' : '' }}">@if ($cell['conflict'])⚠ @endif{{ $cell['label'] }}</span>
                                                                <button type="button" wire:click="removeBlock({{ $cell['id'] }})"
                                                                    wire:loading.attr="disabled" wire:target="removeBlock({{ $cell['id'] }})"
                                                                    title="Eliminar este bloque ({{ $blockTime }})"
                                                                    aria-label="Eliminar el bloque de {{ $cell['label'] }} del {{ mb_strtolower($label) }} de {{ $blockTime }}"
                                                                    class="educon-block-delete">
                                                                    <svg style="height: 12px; width: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                </button>
                                                            </div>
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
