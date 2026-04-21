<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Aprobación de Sílabos
                <span class="text-gray-500 font-normal text-base ml-2">
                    Periodo: {{ $activePeriod?->name ?? 'N/A' }}
                </span>
            </h2>

            {{-- Indicador de carrera del coordinador --}}
            @if($coordinatorCareerId && !auth()->user()->hasRole('Administrador'))
                <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-100
                             text-purple-800 text-sm font-medium rounded-full border border-purple-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13
                                 C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13
                                 C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13
                                 C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    Viendo sílabos de tu carrera asignada
                </span>
            @elseif(auth()->user()->hasRole('Administrador'))
                <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-100
                             text-red-800 text-sm font-medium rounded-full border border-red-200">
                    Administrador — Todas las carreras
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">

                    @if(!$activePeriod)
                        {{-- Sin periodo activo --}}
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-3 text-red-500 font-medium">No hay un periodo académico activo.</p>
                        </div>

                    @elseif(!$coordinatorCareerId && !auth()->user()->hasRole('Administrador'))
                        {{-- Coordinador sin carrera asignada --}}
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                            <p class="mt-3 text-yellow-700 font-medium">
                                No tienes una carrera asignada como coordinador.
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                Contacta al administrador para que te asigne una carrera.
                            </p>
                        </div>

                    @else
                        {{-- CONTENIDO PRINCIPAL --}}

                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                                    <div class="w-full md:w-1/3">
                                        <x-input type="text" wire:model.live.debounce.300ms="search" 
                                                placeholder="Buscar curso o docente..." class="w-full" />
                                    </div>

                                    <div class="flex bg-gray-100 p-1 rounded-lg">
                                        <button wire:click="setTab('pending')" 
                                            class="px-4 py-2 text-sm font-medium rounded-md transition-all {{ $activeTab === 'pending' ? 'bg-white shadow text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                                            Por Aprobar
                                        </button>
                                        <button wire:click="setTab('approved')" 
                                            class="px-4 py-2 text-sm font-medium rounded-md transition-all {{ $activeTab === 'approved' ? 'bg-white shadow text-emerald-600' : 'text-gray-500 hover:text-gray-700' }}">
                                            Aprobados
                                        </button>
                                        <button wire:click="setTab('drafts')" 
                                            class="px-4 py-2 text-sm font-medium rounded-md transition-all {{ $activeTab === 'drafts' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700' }}">
                                            Borradores (En edición)
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curso</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Docente</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Carrera / Turno</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                @if($activeTab === 'pending')
                                                    Enviado / Observado
                                                @elseif($activeTab === 'approved')
                                                    Aprobado
                                                @else
                                                    Última Edición
                                                @endif
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @forelse($syllabi as $syllabus)
                                            @php
                                                $assignment = $syllabus->teacherAssignment;
                                                $teacher = $assignment->teacher->user;
                                                $unit = $assignment->didacticUnit;
                                                $career = $unit->module->studyPlan->career;
                                            @endphp
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-bold text-gray-900">{{ $unit->name }}</div>
                                                    <div class="text-xs text-gray-500">ID: {{ $syllabus->id }}</div>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-700">
                                                    {{ $teacher->lastname }}, {{ $teacher->name }}
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900">{{ $career->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $assignment->shift->name }}</div>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    @if($activeTab === 'pending')
                                                        {{ $syllabus->submitted_at?->format('d/m/Y H:i') ?? $syllabus->updated_at?->format('d/m/Y H:i') }}
                                                    @elseif($activeTab === 'approved')
                                                        {{ $syllabus->approved_at?->format('d/m/Y H:i') }}
                                                    @else
                                                        {{ $syllabus->updated_at?->format('d/m/Y H:i') }}
                                                    @endif
                                                </td>

                                                <td class="px-6 py-4 text-right text-sm font-medium space-x-3">
                                                    <a href="{{ route('teacher.syllabus.pdf', $syllabus) }}" target="_blank" 
                                                    class="text-blue-600 hover:text-blue-900" title="Ver PDF">
                                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    </a>

                                                    @if($activeTab === 'pending')
                                                        <button wire:click="approve({{ $syllabus->id }})" wire:confirm="¿Aprobar este sílabo?" class="text-green-600 hover:text-green-900">Aprobar</button>
                                                        <button wire:click="openObserveModal({{ $syllabus->id }})" class="text-yellow-600 hover:text-yellow-900">Observar</button>
                                                    @elseif($activeTab === 'approved')
                                                        <x-dropdown align="right" width="48">
                                                            <x-slot name="trigger">
                                                                <button class="text-gray-500 hover:text-gray-700 font-bold text-xs uppercase tracking-widest">
                                                                    Cambiar Estado ▾
                                                                </button>
                                                            </x-slot>
                                                            <x-slot name="content">
                                                                <div class="block px-4 py-2 text-xs text-gray-400">Opciones de Reversión</div>
                                                                <x-dropdown-link class="cursor-pointer" wire:click="changeStatus({{ $syllabus->id }}, 'draft')" wire:confirm="¿Revertir a borrador?">
                                                                    Revertir a Borrador
                                                                </x-dropdown-link>
                                                                <x-dropdown-link class="cursor-pointer" wire:click="openObserveModal({{ $syllabus->id }})">
                                                                    Revertir a Observado
                                                                </x-dropdown-link>
                                                            </x-slot>
                                                        </x-dropdown>
                                                    @else
                                                        <span class="text-xs text-gray-400 italic">En edición por docente</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 italic">
                                                    No se encontraron sílabos {{ $activeTab === 'pending' ? 'pendientes' : 'aprobados' }}.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($syllabi->hasPages())
                                <div class="p-6 bg-gray-50 border-t border-gray-200">
                                    {{ $syllabi->links() }}
                                </div>
                            @endif
                        </div>

                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================
         MODAL DE OBSERVACIÓN
         ============================================ --}}
    <x-dialog-modal wire:model.live="isObserveModalOpen">

        <x-slot name="title">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                Observar Sílabo
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="space-y-4">

                {{-- Info del sílabo observado --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <p class="text-xs text-yellow-700 font-medium uppercase tracking-wide mb-1">
                        Curso
                    </p>
                    <p class="font-semibold text-gray-900">
                        {{ $syllabusToObserve?->teacherAssignment?->didacticUnit?->name ?? '—' }}
                    </p>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Docente:
                        {{ trim(
                            ($syllabusToObserve?->teacherAssignment?->teacher?->user?->name ?? '') . ' ' .
                            ($syllabusToObserve?->teacherAssignment?->teacher?->user?->lastname ?? '')
                        ) }}
                    </p>
                </div>

                {{-- Textarea de observación --}}
                <div>
                    <x-label for="observationNotes"
                             value="Motivo de la Observación (visible para el docente) *" />
                    <textarea
                        id="observationNotes"
                        wire:model.blur="observationNotes"
                        rows="5"
                        placeholder="Describe detalladamente qué debe corregir el docente..."
                        class="mt-1 block w-full border-gray-300 focus:border-yellow-500
                               focus:ring-yellow-500 rounded-md shadow-sm text-sm resize-none">
                    </textarea>
                    <x-input-error for="observationNotes" class="mt-1" />
                    <p class="text-xs text-gray-400 mt-1">
                        Mínimo 10 caracteres. El docente verá este mensaje en su editor de sílabo.
                    </p>
                </div>

            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="closeModal">
                Cancelar
            </x-secondary-button>
            <button
                wire:click="saveObservation"
                wire:loading.attr="disabled"
                wire:target="saveObservation"
                class="ml-3 inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600
                       text-white text-sm font-semibold rounded-md transition-colors
                       disabled:opacity-50 disabled:cursor-not-allowed">
                <svg wire:loading wire:target="saveObservation"
                     class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                     fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Guardar Observación
            </button>
        </x-slot>

    </x-dialog-modal>
</div>