<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gestión de Horarios
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex border-b border-gray-200 mb-6 bg-white rounded-t-lg shadow-sm">
                {{-- [MODIFICADO] La gestión parte del docente y de su carga académica --}}
                <button wire:click="setViewMode('teacher')"
                    class="py-3 px-6 font-medium text-sm focus:outline-none {{ $viewMode === 'teacher' ? 'border-b-2 border-indigo-600 text-indigo-600 bg-indigo-50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Por docente
                    </span>
                </button>
                <button wire:click="setViewMode('consolidated')"
                    class="py-3 px-6 font-medium text-sm focus:outline-none {{ $viewMode === 'consolidated' ? 'border-b-2 border-purple-600 text-purple-600 bg-purple-50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                        Vista Consolidada Semestral
                    </span>
                </button>
            </div>
            @if($viewMode === 'teacher')

                @if(!$activePeriod)
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow">
                        <p class="font-bold">No hay periodo académico activo.</p>
                        <p>Active un periodo para gestionar horarios.</p>
                    </div>
                @else
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-indigo-100 text-indigo-800">
                            Periodo activo: <strong class="ml-1">{{ $activePeriod->name }}</strong>
                        </span>
                        <x-button wire:click="$set('showExportModal', true)" class="bg-green-600 hover:bg-green-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Exportar Horarios
                        </x-button>
                    </div>

                    {{-- PASOS 1 Y 2: PROGRAMA Y DOCENTE --}}
                    <div class="bg-white shadow-xl sm:rounded-lg p-6 border-l-4 border-indigo-500">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-label value="1. Programa de Estudios" class="mb-1 text-indigo-700 font-bold" />
                                <select wire:model.live="selectedCareerId" class="w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">-- Seleccione --</option>
                                    @foreach($careers as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label value="2. Docente" class="mb-1 text-indigo-700 font-bold" />
                                <select wire:model.live="selectedTeacherId" class="w-full border-gray-300 rounded-md shadow-sm" @disabled(!$selectedCareerId || $this->programTeachers->isEmpty())>
                                    <option value="">-- Seleccione --</option>
                                    @foreach($this->programTeachers as $t)
                                        <option value="{{ $t->id }}">
                                            {{ $t->user->lastname ?? '' }}, {{ $t->user->name ?? '' }} ({{ $t->assignments_count }} {{ $t->assignments_count === 1 ? 'sección' : 'secciones' }})
                                        </option>
                                    @endforeach
                                </select>
                                @if($selectedCareerId && $this->programTeachers->isEmpty())
                                    <p class="text-sm text-amber-700 mt-2">
                                        Sin carga académica en este programa para el periodo activo.
                                        <a href="{{ route('academic-process.teacher-assignments') }}" class="underline font-semibold">Ir a carga académica</a>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($selectedTeacherId)
                        @php
                            $grid = $this->teacherGrid;
                            $assignments = $this->teacherAssignments;
                            $current = $this->selectedAssignment;
                            $currentInfo = $current ? $assignments->firstWhere('id', $current->id) : null;
                            $hoursOf = fn ($minutes) => app(\App\Services\ScheduleValidationService::class)->formatHours((int) $minutes);
                            $dayLabels = ['monday' => 'Lunes', 'tuesday' => 'Martes', 'wednesday' => 'Miércoles', 'thursday' => 'Jueves', 'friday' => 'Viernes', 'saturday' => 'Sábado'];
                            // Paleta por sección (estilos en línea: no dependen del build de Tailwind)
                            $palette = [
                                ['#eff6ff', '#3b82f6', '#1e3a8a'], ['#f5f3ff', '#8b5cf6', '#4c1d95'], ['#ecfdf5', '#10b981', '#064e3b'],
                                ['#fff7ed', '#f97316', '#7c2d12'], ['#fdf2f8', '#ec4899', '#831843'], ['#f0fdfa', '#14b8a6', '#134e4a'],
                            ];
                            $colorOf = fn ($assignmentId) => $palette[$assignments->search(fn ($a) => $a->id === $assignmentId) % count($palette)] ?? $palette[0];
                            $toMinutes = fn ($hhmm) => ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2);
                            $fromMin = intdiv($toMinutes($grid['from']), 60) * 60;
                            $toMin = (int) ceil($toMinutes($grid['to']) / 60) * 60;
                            $spanMin = max(60, $toMin - $fromMin);
                            $pxPerMin = 1; // 60 px por hora
                        @endphp

                        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                            {{-- PASO 4: UNIDADES DIDÁCTICAS DEL DOCENTE Y FORMULARIO --}}
                            <div class="lg:col-span-1 space-y-4">
                                <div class="bg-white shadow-xl sm:rounded-lg p-4">
                                    <h3 class="font-bold text-gray-800 mb-3 border-b pb-2">3. Unidades didácticas</h3>
                                    <div class="space-y-2">
                                        @foreach($assignments as $a)
                                            @php
                                                $weeklyMin = (int) round(((float) ($a->didacticUnit->weekly_hours ?? 0)) * 60);
                                                $percent = $weeklyMin > 0 ? min(100, round($a->scheduled_minutes / $weeklyMin * 100)) : 0;
                                                $over = $weeklyMin > 0 && $a->scheduled_minutes > $weeklyMin;
                                                [$bg, $border, $text] = $colorOf($a->id);
                                            @endphp
                                            <button type="button" wire:key="assignment-{{ $a->id }}" wire:click="selectAssignment({{ $a->id }})"
                                                class="w-full text-left p-2 rounded border text-sm transition {{ $current?->id === $a->id ? 'ring-2 ring-indigo-500' : 'hover:shadow' }}"
                                                style="background: {{ $bg }}; border-left: 4px solid {{ $border }}; color: {{ $text }};">
                                                <span class="font-semibold block">{{ $a->didacticUnit->name }}</span>
                                                <span class="text-xs block">Semestre {{ $a->didacticUnit->semester }} · Sección {{ $a->section }} · {{ $a->shift->name ?? 'Sin turno' }}</span>
                                                <span class="text-xs block mt-1">
                                                    {{ $hoursOf($a->scheduled_minutes) }} / {{ $weeklyMin > 0 ? $hoursOf($weeklyMin) : '—' }} h
                                                </span>
                                                @if($weeklyMin > 0)
                                                    <span class="block h-1.5 bg-white rounded mt-1"><span class="block h-1.5 rounded" style="width: {{ $percent }}%; background: {{ $over ? '#dc2626' : $border }};"></span></span>
                                                @endif
                                                @if($over)
                                                    <span class="text-xs font-semibold block mt-1" style="color:#b91c1c;">Supera las horas semanales de la UD.</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                @if($current)
                                    <div class="bg-white shadow-xl sm:rounded-lg p-4 sticky top-24">
                                        <h3 class="font-bold text-gray-800 mb-1 border-b pb-2">
                                            {{ $editingScheduleId ? 'Editar bloque' : 'Agregar bloque' }}
                                        </h3>
                                        <p class="text-xs text-gray-500 mb-3">
                                            {{ $current->didacticUnit->name }} · Sección {{ $current->section }}
                                            @if($current->shift?->start_time)
                                                · {{ $current->shift->name }} ({{ $current->shift->start_time->format('H:i') }}–{{ $current->shift->end_time->format('H:i') }})
                                            @endif
                                        </p>

                                        <div class="space-y-3">
                                            <div>
                                                <x-label value="Día" />
                                                <select wire:model="day_of_week" class="w-full border-gray-300 rounded-md">
                                                    @foreach($dayLabels as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <x-label value="Inicio" />
                                                    <x-input type="time" wire:model="start_time" class="w-full"
                                                        min="{{ $current->shift?->start_time?->format('H:i') }}" max="{{ $current->shift?->end_time?->format('H:i') }}" />
                                                </div>
                                                <div>
                                                    <x-label value="Fin" />
                                                    <x-input type="time" wire:model="end_time" class="w-full"
                                                        min="{{ $current->shift?->start_time?->format('H:i') }}" max="{{ $current->shift?->end_time?->format('H:i') }}" />
                                                </div>
                                            </div>
                                            <x-input-error for="start_time" />
                                            <x-input-error for="end_time" />

                                            @if($current->shift?->start_time)
                                                <button type="button" wire:click="useShiftHours" class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-200">
                                                    Usar horario del turno
                                                </button>
                                            @endif

                                            <div>
                                                <x-label value="Aula (opcional)" />
                                                <select wire:model="classroom_resource_id" class="w-full border-gray-300 rounded-md text-sm">
                                                    <option value="">-- Sin aula --</option>
                                                    @foreach($classrooms as $cr)
                                                        <option value="{{ $cr->id }}">{{ $cr->name }} ({{ $cr->type }})</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error for="classroom_resource_id" />
                                            </div>

                                            <div class="text-xs rounded p-2 border bg-gray-50 border-gray-200 text-gray-700">
                                                @if($currentInfo && $currentInfo->remaining_minutes !== null)
                                                    Quedan {{ $hoursOf(max(0, $currentInfo->remaining_minutes)) }} h de {{ $hoursOf(round($current->didacticUnit->weekly_hours * 60)) }} h semanales.
                                                @endif
                                                @foreach($currentInfo->warnings ?? [] as $warning)
                                                    <span class="block text-amber-700">{{ $warning }}</span>
                                                @endforeach
                                            </div>

                                            <button wire:click="saveSchedule" wire:loading.attr="disabled" wire:target="saveSchedule"
                                                class="w-full {{ $editingScheduleId ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-600 hover:bg-blue-700' }} text-white font-bold py-2 px-4 rounded shadow disabled:opacity-50">
                                                {{ $editingScheduleId ? 'Guardar cambios' : '+ Agregar' }}
                                            </button>
                                            @if($editingScheduleId)
                                                <div class="flex justify-between">
                                                    <button wire:click="cancelEdit" class="text-sm text-gray-600 hover:text-gray-900 underline">Cancelar edición</button>
                                                    <button wire:click="confirmDeleteSchedule({{ $editingScheduleId }})" class="text-sm text-red-600 hover:text-red-800 underline">Eliminar bloque</button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="text-sm text-gray-500 bg-white rounded-lg border border-dashed border-gray-300 p-4 text-center">
                                        Seleccione una unidad didáctica para programar sus bloques.
                                    </div>
                                @endif
                            </div>

                            {{-- PASO 3: HORARIO SEMANAL DEL DOCENTE --}}
                            <div class="lg:col-span-3">
                                <div class="bg-white shadow-xl sm:rounded-lg p-5 overflow-x-auto">
                                    <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                                        <h3 class="font-bold text-gray-800">Horario semanal del docente</h3>
                                        <div class="flex gap-3 text-xs text-gray-600">
                                            <span class="inline-flex items-center"><span class="w-3 h-3 rounded mr-1" style="background:#e5e7eb; border-left:3px solid #9ca3af;"></span> Otro programa (solo lectura)</span>
                                            <span class="inline-flex items-center">⚠ Fuera del turno de la sección</span>
                                        </div>
                                    </div>

                                    <div class="min-w-[900px]" style="display: grid; grid-template-columns: 56px repeat(6, minmax(0, 1fr)); gap: 6px;">
                                        <div></div>
                                        @foreach($grid['days'] as $day)
                                            <div class="text-center font-bold bg-indigo-50 p-2 rounded text-sm text-gray-700">{{ $dayLabels[$day] }}</div>
                                        @endforeach

                                        {{-- Columna de horas --}}
                                        <div class="relative" style="height: {{ $spanMin * $pxPerMin }}px;">
                                            @for($m = $fromMin; $m < $toMin; $m += 60)
                                                <div class="absolute text-xs text-gray-500" style="top: {{ ($m - $fromMin) * $pxPerMin }}px;">{{ sprintf('%02d:00', intdiv($m, 60)) }}</div>
                                            @endfor
                                        </div>

                                        @foreach($grid['days'] as $day)
                                            <div class="relative bg-gray-50 rounded border border-gray-200" style="height: {{ $spanMin * $pxPerMin }}px;">
                                                @for($m = $fromMin + 60; $m < $toMin; $m += 60)
                                                    <div class="absolute left-0 right-0 border-t border-dashed border-gray-200" style="top: {{ ($m - $fromMin) * $pxPerMin }}px;"></div>
                                                @endfor

                                                @foreach($grid['blocks'][$day] as $b)
                                                    @php
                                                        [$bg, $border, $text] = $b['editable'] ? $colorOf($b['assignment_id']) : ['#f3f4f6', '#9ca3af', '#4b5563'];
                                                        $top = ($toMinutes($b['start']) - $fromMin) * $pxPerMin;
                                                        $height = max(24, ($toMinutes($b['end']) - $toMinutes($b['start'])) * $pxPerMin);
                                                    @endphp
                                                    <div wire:key="block-{{ $b['id'] }}"
                                                        @if($b['editable']) wire:click="editSchedule({{ $b['id'] }})" title="Editar bloque" @else title="{{ $b['program'] }} (solo lectura)" @endif
                                                        class="absolute left-1 right-1 rounded shadow-sm p-1 overflow-hidden text-xs {{ $b['editable'] ? 'cursor-pointer hover:shadow-md' : '' }} {{ (int) $editingScheduleId === $b['id'] ? 'ring-2 ring-amber-500' : '' }}"
                                                        style="top: {{ $top }}px; height: {{ $height }}px; background: {{ $bg }}; border-left: 4px solid {{ $border }}; color: {{ $text }};">
                                                        <div class="font-bold">
                                                            @if($b['out_of_shift'])<span title="Fuera del turno de la sección">⚠</span>@endif
                                                            {{ $b['start'] }}–{{ $b['end'] }}
                                                        </div>
                                                        <div class="truncate">{{ $b['unit'] }} · {{ $b['section'] }}</div>
                                                        <div class="truncate opacity-75">{{ $b['editable'] ? ($b['classroom'] ?? 'Sin aula') : $b['program'] }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12 bg-white rounded-lg shadow border border-dashed border-gray-300">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Seleccione un programa y un docente</h3>
                            <p class="mt-1 text-sm text-gray-500">Se mostrará su horario semanal y las unidades didácticas de su carga académica.</p>
                        </div>
                    @endif

                @endif {{-- FIN DEL IF DE activePeriod --}}
            @else
                
                {{-- NUEVA VISTA CONSOLIDADA SEMESTRAL --}}
                <div class="space-y-6">
                    
                    {{-- Filtros Consolidados --}}
                    <div class="bg-white shadow-xl sm:rounded-lg p-6 border-l-4 border-purple-500">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <x-label value="1. Programa de Estudios" class="text-purple-700 font-bold" />
                                <select wire:model.live="selectedCareerId" class="w-full border-gray-300 rounded-md mt-1">
                                    <option value="">-- Seleccione --</option>
                                    @foreach($careers as $c) 
                                        <option value="{{ $c->id }}">{{ $c->name }}</option> 
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label value="2. Semestre / Ciclo" class="text-purple-700 font-bold" />
                                <select wire:model.live="filterCycleId" class="w-full border-gray-300 rounded-md mt-1" {{ !$selectedCareerId ? 'disabled' : '' }}>
                                    <option value="">-- Seleccione --</option>
                                    @for($i = 1; $i <= 6; $i++)
                                        <option value="{{ $i }}">Semestre {{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div>
                                <x-label value="3. Turno" class="text-purple-700 font-bold" />
                                <select wire:model.live="filterShiftId" class="w-full border-gray-300 rounded-md mt-1" {{ !$filterCycleId ? 'disabled' : '' }}>
                                    <option value="">-- Seleccione --</option>
                                    @foreach($shifts as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                {{-- Botón de Exportar Excel (Apunta a una ruta que crearemos luego en el Paso 3/4) --}}
                                {{-- [CORREGIDO] Sin periodo activo no hay enlaces (antes: error 500 por $activePeriod->id) --}}
                                @if($activePeriod && $selectedCareerId && $filterCycleId && $filterShiftId)
                                    <div class="flex flex-wrap gap-2 items-center">
                                        {{-- Botón Excel --}}
                                        <a href="{{ route('academic-process.schedules.export-consolidated', [
                                            'career' => $selectedCareerId, 
                                            'cycle' => $filterCycleId, 
                                            'shift' => $filterShiftId,
                                            'period_id' => $activePeriod->id
                                        ]) }}" 
                                        title="Descargar Excel"
                                        aria-label="Descargar Excel"
                                        class="bg-green-600 text-white p-2 rounded hover:bg-green-700 transition">
                                            {{-- Heroicon: document-arrow-down (similar a Excel) --}}
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </a>

                                        {{-- Botón PDF --}}
                                        <a href="{{ route('academic-process.schedules.export-consolidated-pdf', [
                                            'career' => $selectedCareerId, 
                                            'cycle' => $filterCycleId, 
                                            'shift' => $filterShiftId,
                                            'period_id' => $activePeriod->id
                                        ]) }}" 
                                        title="Descargar PDF"
                                        aria-label="Descargar PDF"
                                        class="bg-red-600 text-white p-2 rounded hover:bg-red-700 transition">
                                            {{-- Heroicon: document-text (representa PDF) --}}
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </a>

                                        {{-- Botón de reseteo --}}
                                        {{-- [MODIFICADO] Confirmación con SweetAlert e indicando cuántos bloques se eliminan --}}
                                        <button wire:click="confirmResetSemesterSchedules"
                                                title="Reiniciar horarios del semestre"
                                                aria-label="Reiniciar horarios del semestre {{ $filterCycleId }}"
                                                class="bg-red-700 text-white p-2 rounded hover:bg-red-800 transition border-2 border-red-300">
                                            {{-- Heroicon: trash --}}
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($selectedCareerId && $filterCycleId && $filterShiftId)
                        {{-- Indicadores de Avance --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                                <p class="text-sm text-gray-500 font-semibold">Cursos del Semestre</p>
                                <p class="text-2xl font-bold text-gray-800">{{ $this->progressStats['total_courses'] }}</p>
                            </div>
                            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
                                <p class="text-sm text-gray-500 font-semibold">Cursos Programados (Este Turno)</p>
                                <p class="text-2xl font-bold text-gray-800">{{ $this->progressStats['scheduled_courses'] }}</p>
                            </div>
                            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                                <p class="text-sm text-gray-500 font-semibold">Avance de Programación</p>
                                <div class="flex items-center mt-1">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                        <div class="bg-green-600 h-2.5 rounded-full" style="width: {{ $this->progressStats['percentage'] }}%"></div>
                                    </div>
                                    <span class="text-sm font-bold text-gray-700">{{ $this->progressStats['percentage'] }}%</span>
                                </div>
                            </div>
                        </div>

                        {{-- Grilla Maestra --}}
                        <div class="bg-white shadow-xl sm:rounded-lg p-5 overflow-x-auto">
                            <div class="grid grid-cols-7 gap-2 min-w-[1000px]">
                                {{-- Columna Horas --}}
                                <div class="space-y-[60px] pt-8">
                                    <div class="text-xs font-semibold text-gray-600 text-center bg-gray-50 p-1 rounded">Hora</div>
                                    @for($h = 7; $h <= 21; $h++)
                                        <div class="text-xs text-gray-500 text-center py-1 border-t">{{ sprintf('%02d:00', $h) }}</div>
                                    @endfor
                                </div>

                                {{-- Días --}}
                                @foreach(['monday' => 'Lunes', 'tuesday' => 'Martes', 'wednesday' => 'Miércoles', 'thursday' => 'Jueves', 'friday' => 'Viernes', 'saturday' => 'Sábado'] as $dayKey => $dayLabel)
                                    <div>
                                        <div class="text-center font-bold bg-purple-100 p-2 rounded text-sm text-purple-900 mb-2">{{ $dayLabel }}</div>
                                        <div class="relative min-h-[850px] bg-gray-50 p-2 rounded border border-gray-200">
                                            @php
                                                $daySchedules = $this->consolidatedSchedule->get($dayKey, collect());
                                            @endphp

                                            @foreach($daySchedules as $sched)
                                                @php
                                                    $startHour = (int)$sched->start_time->format('H');
                                                    $startMin = (int)$sched->start_time->format('i');
                                                    $topPercent = (($startHour - 7) + ($startMin / 60)) / 15 * 100;
                                                @endphp

                                                <div class="absolute w-[95%] border-l-4 border-purple-500 bg-white shadow-md p-2 rounded z-10 text-xs overflow-hidden hover:z-20 hover:scale-105 transition-all"
                                                     style="top: {{ $topPercent }}%;">
                                                    <div class="font-bold text-purple-800 line-clamp-2" title="{{ $sched->teacherAssignment->didacticUnit->name }}">
                                                        {{ $sched->teacherAssignment->didacticUnit->name }}
                                                    </div>
                                                    <div class="text-gray-600 mt-1 font-semibold">
                                                        🕒 {{ $sched->start_time->format('H:i') }} - {{ $sched->end_time->format('H:i') }}
                                                    </div>
                                                    <div class="text-gray-600 truncate mt-1">
                                                        👨‍🏫 {{ $sched->teacherAssignment->teacher->user->lastname }}
                                                    </div>
                                                    <div class="text-gray-500 font-medium">
                                                        📍 {{ $sched->classroomResource->name ?? 'Sin aula' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12 bg-white rounded-lg shadow border border-dashed border-gray-300">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Seleccione los filtros</h3>
                            <p class="mt-1 text-sm text-gray-500">Seleccione Programa, Semestre y Turno para ver el horario maestro.</p>
                        </div>
                    @endif
                </div>

            @endif
        </div>
    </div>

    {{-- MODAL DE EXPORTACIÓN --}}
    <x-dialog-modal wire:model.live="showExportModal" maxWidth="2xl">
        <x-slot name="title">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Exportar Horarios
            </div>
        </x-slot>
        
        <x-slot name="content">
            <div class="space-y-6">
                
                {{-- SELECTOR DE TIPO DE REPORTE --}}
                <div>
                    <x-label value="Tipo de Reporte" class="mb-3 text-lg font-bold text-gray-800" />
                    <div class="grid grid-cols-2 gap-4">
                        
                        {{-- OPCIÓN 1: POR DOCENTE --}}
                        <label class="relative flex flex-col cursor-pointer rounded-lg border-2 p-5 hover:border-green-500 transition {{ $exportType === 'teacher' ? 'border-green-600 bg-green-50' : 'border-gray-300 bg-white' }}">
                            <input type="radio" wire:model.live="exportType" value="teacher" class="sr-only">
                            <div class="flex items-center mb-3">
                                <div class="text-4xl mr-3">👨‍🏫</div>
                                <div>
                                    <p class="text-base font-bold text-gray-900">Por Docente</p>
                                    <p class="text-xs text-gray-600">Carga horaria individual</p>
                                </div>
                            </div>
                            @if($exportType === 'teacher')
                                <div class="absolute top-3 right-3">
                                    <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            @endif
                        </label>

                        {{-- OPCIÓN 2: CONSOLIDADO POR PROGRAMA --}}
                        <label class="relative flex flex-col cursor-pointer rounded-lg border-2 p-5 hover:border-purple-500 transition {{ $exportType === 'career' ? 'border-purple-600 bg-purple-50' : 'border-gray-300 bg-white' }}">
                            <input type="radio" wire:model.live="exportType" value="career" class="sr-only">
                            <div class="flex items-center mb-3">
                                <div class="text-4xl mr-3">📚</div>
                                <div>
                                    <p class="text-base font-bold text-gray-900">Consolidado</p>
                                    <p class="text-xs text-gray-600">Por programa y turno</p>
                                </div>
                            </div>
                            @if($exportType === 'career')
                                <div class="absolute top-3 right-3">
                                    <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            @endif
                        </label>
                    </div>
                </div>

                {{-- FILTROS SEGÚN TIPO --}}
                <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    
                    @if($exportType === 'teacher')
                        {{-- FILTROS PARA DOCENTE --}}
                        <div>
                            <x-label value="Seleccionar Docente" class="mb-2 font-bold text-gray-700" />
                            <select wire:model.live="exportTeacherId" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500">
                                <option value="">-- Seleccione un docente --</option>
                                @foreach($teachers->sortBy('user.lastname') as $teacher)
                                    <option value="{{ $teacher->id }}">
                                        {{ $teacher->user->lastname }}, {{ $teacher->user->name }} - DNI: {{ $teacher->user->document_number }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error for="exportTeacherId" class="mt-1" />
                            
                            @if($exportTeacherId)
                                <div class="mt-3 bg-green-100 border border-green-300 rounded p-3 text-sm text-green-800">
                                    <strong>✓ Docente seleccionado:</strong> 
                                    {{ $teachers->find($exportTeacherId)?->user->name }} 
                                    {{ $teachers->find($exportTeacherId)?->user->lastname }}
                                </div>
                            @endif
                        </div>

                    @elseif($exportType === 'career')
                        {{-- FILTROS PARA PROGRAMA --}}
                        <div class="space-y-4">
                            <div>
                                <x-label value="Programa de Estudios" class="mb-2 font-bold text-gray-700" />
                                <select wire:model.live="exportCareerId" class="w-full border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Seleccione programa --</option>
                                    @foreach($careers as $career)
                                        <option value="{{ $career->id }}">{{ $career->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error for="exportCareerId" class="mt-1" />
                            </div>

                            @if($exportCareerId)
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-label value="Semestre" class="mb-2 font-bold text-gray-700" />
                                        <select wire:model.live="exportSemester" class="w-full border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <option value="">-- Todos los semestres --</option>
                                            @for($i = 1; $i <= 6; $i++)
                                                <option value="{{ $i }}">Semestre {{ $i }}</option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div>
                                        <x-label value="Turno" class="mb-2 font-bold text-gray-700" />
                                        <select wire:model.live="exportShiftId" class="w-full border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <option value="">-- Todos los turnos --</option>
                                            @foreach($shifts as $shift)
                                                <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                @if($exportCareerId)
                                    <div class="bg-purple-100 border border-purple-300 rounded p-3 text-sm text-purple-800">
                                        <strong>✓ Programa seleccionado:</strong><br>
                                        {{ $careers->find($exportCareerId)?->name }}
                                        @if($exportSemester)
                                            - Semestre {{ $exportSemester }}
                                        @endif
                                        @if($exportShiftId)
                                            - {{ $shifts->find($exportShiftId)?->name }}
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>

                {{-- SELECTOR DE FORMATO --}}
                <div>
                    <x-label value="Formato de Exportación" class="mb-2 font-bold text-gray-700" />
                    <div class="flex gap-3">
                        <label class="flex items-center px-4 py-3 border-2 rounded-lg cursor-pointer {{ $exportFormat === 'pdf' ? 'border-red-500 bg-red-50' : 'border-gray-300 bg-white' }} hover:border-red-400 transition">
                            <input type="radio" wire:model.live="exportFormat" value="pdf" class="mr-2">
                            <svg class="w-5 h-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                            </svg>
                            <span class="font-semibold">PDF</span>
                        </label>

                        <label class="flex items-center px-4 py-3 border-2 rounded-lg cursor-pointer {{ $exportFormat === 'excel' ? 'border-green-500 bg-green-50' : 'border-gray-300 bg-white' }} hover:border-green-400 transition">
                            <input type="radio" wire:model.live="exportFormat" value="excel" class="mr-2">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/>
                                <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                            </svg>
                            <span class="font-semibold">Excel</span>
                        </label>
                    </div>
                </div>

            </div>
        </x-slot>
        
        <x-slot name="footer">
            <x-secondary-button wire:click="$set('showExportModal', false)">
                Cancelar
            </x-secondary-button>
            
            <x-button 
                class="ml-3 {{ !$this->canExport ? 'opacity-50 cursor-not-allowed' : '' }}" 
                wire:click="generateReport"
                :disabled="!$this->canExport">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Generar Reporte
            </x-button>
        </x-slot>
    </x-dialog-modal>

    {{-- @push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('open-in-new-tab', (event) => {
                const url = event.url || event[0]?.url || event;
                
                if (url) {
                    const newWindow = window.open(url, '_blank');
                    
                    if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Popup bloqueado',
                            text: 'Su navegador bloqueó la ventana emergente.',
                            confirmButtonText: 'Abrir en esta pestaña',
                            showCancelButton: true,
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = url;
                            }
                        });
                    }
                }
            });
        });
    </script>
    @endpush --}}
    {{-- @push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            // Listener alternativo si el método js() falla
            Livewire.on('open-report', (event) => {
                const url = event.url || event[0]?.url;
                if (url) {
                    window.open(url, '_blank');
                }
            });
        });
    </script>
    @endpush --}}
</div>