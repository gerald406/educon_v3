<div class="min-h-screen bg-gray-100 pb-10">
    {{-- Header Fijo (Estilo App) --}}
    <div class="bg-indigo-700 text-white p-4 shadow-md sticky top-0 z-50">
        <h1 class="text-xl font-bold text-center">Monitoreo de Aulas</h1>
        <p class="text-xs text-indigo-200 text-center mt-1">
            {{ $activePeriod ? 'Periodo: ' . $activePeriod->name : 'Sin periodo activo' }}
        </p>
    </div>

    @if(!$activePeriod)
        <div class="p-5 mt-10">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm">
                <p class="font-bold">Sistema no disponible</p>
                <p class="text-sm">No hay un periodo académico activo en este momento.</p>
            </div>
        </div>
    @else
        <div class="max-w-md mx-auto p-4 space-y-4">
            
            {{-- Panel de Filtros (Botones grandes para dedos) --}}
            <div class="bg-white rounded-xl shadow-sm p-5 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Programa</label>
                    <select wire:model.live="selectedCareerId" class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-3">
                        <option value="">Seleccione un programa...</option>
                        @foreach($careers as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Ciclo</label>
                        <select wire:model.live="filterCycleId" class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-3" {{ !$selectedCareerId ? 'disabled' : '' }}>
                            <option value="">--</option>
                            @for($i = 1; $i <= 6; $i++) <option value="{{ $i }}">Ciclo {{ $i }}</option> @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Turno</label>
                        <select wire:model.live="filterShiftId" class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-3" {{ !$filterCycleId ? 'disabled' : '' }}>
                            <option value="">--</option>
                            @foreach($shifts as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @if($selectedCareerId && $filterCycleId && $filterShiftId)
                {{-- Selector de Días (Scroll horizontal en móviles) --}}
                <div class="flex overflow-x-auto py-2 gap-2 snap-x hide-scroll-bar">
                    @php
                        $days = [
                            'monday' => 'Lunes', 'tuesday' => 'Martes', 
                            'wednesday' => 'Miérc.', 'thursday' => 'Jueves', 
                            'friday' => 'Viernes', 'saturday' => 'Sábado'
                        ];
                    @endphp
                    @foreach($days as $key => $label)
                        <button wire:click="setDay('{{ $key }}')" 
                                class="snap-start shrink-0 px-4 py-2 rounded-full text-sm font-bold transition-colors 
                                {{ $selectedDay === $key ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Lista Vertical de Horarios (Timeline) --}}
                <div class="space-y-3 mt-4">
                    @forelse($this->dailySchedule as $sched)
                        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ (int)$sched->start_time->format('H') < 14 ? 'border-blue-400' : 'border-purple-500' }} relative overflow-hidden">
                            
                            {{-- Fila Superior: Hora y Aula --}}
                            <div class="flex justify-between items-center mb-2">
                                <div class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-md text-sm font-black flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ $sched->start_time->format('H:i') }} - {{ $sched->end_time->format('H:i') }}
                                </div>
                                <div class="text-xs font-bold text-gray-500 bg-gray-100 px-2 py-1 rounded border">
                                    📍 Aula: <span class="text-gray-800">{{ $sched->classroomResource->name ?? 'Por asignar' }}</span>
                                </div>
                            </div>

                            {{-- Curso --}}
                            <h3 class="font-bold text-gray-900 text-lg leading-tight mb-1">
                                {{ $sched->teacherAssignment->didacticUnit->name }}
                            </h3>

                            {{-- Docente a Monitorear --}}
                            <div class="flex items-center text-gray-600 mt-2 bg-gray-50 p-2 rounded">
                                <div class="text-xl mr-2">👨‍🏫</div>
                                <div>
                                    <p class="text-xs uppercase text-gray-400 font-bold tracking-wider">Docente Asignado</p>
                                    <p class="text-sm font-bold text-gray-800">
                                        {{ $sched->teacherAssignment->teacher->user->lastname }}, 
                                        {{ $sched->teacherAssignment->teacher->user->name }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10">
                            <div class="text-4xl mb-3 opacity-50">☕</div>
                            <h3 class="text-lg font-medium text-gray-900">Sin clases programadas</h3>
                            <p class="text-sm text-gray-500">No hay horarios registrados para este día.</p>
                        </div>
                    @endforelse
                </div>
            @else
                {{-- Estado inicial --}}
                <div class="text-center py-12 px-4">
                    <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <p class="text-gray-500 font-medium">Complete los filtros arriba para visualizar las clases y ubicar al docente.</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Estilo para ocultar la barra de scroll en los botones de día --}}
    <style>
        .hide-scroll-bar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .hide-scroll-bar::-webkit-scrollbar {
            display: none;
        }
    </style>
</div>