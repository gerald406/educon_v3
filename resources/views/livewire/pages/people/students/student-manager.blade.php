<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de Estudiantes</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <!-- CABECERA: Buscador y Botones -->
                <div class="flex flex-col md:flex-row justify-between mb-6 gap-4">
                    <div class="w-full md:w-1/3">
                        <x-input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar Estudiante..." class="w-full" />
                    </div>
                    <div class="flex space-x-2">
                        <!-- Botón de Importación -->
                        <x-secondary-button wire:click="openImportModal" class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Importar
                        </x-secondary-button>
                        <x-button wire:click="create">Nuevo Estudiante</x-button>
                    </div>
                </div>

                <div class="overflow-x-auto border rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estudiante</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Carrera</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Semestre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($students as $student)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full" src="{{ $student->user->profile_photo_url }}" />
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $student->user->full_name ?? $student->user->name }}</div>
                                                <div class="text-xs text-gray-500">DNI: {{ $student->user->document_number }}</div>
                                                <div class="text-xs text-indigo-600 font-bold">Cód: {{ $student->code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $student->career->name ?? 'Sin Carrera' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        Semestre {{ $student->current_semester }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $student->academic_status === 'regular' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ ucfirst($student->academic_status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium">
                                        <button wire:click="edit({{ $student->id }})" class="text-indigo-600 mr-3">Editar</button>
                                        <button wire:click="confirmDelete({{ $student->id }})" class="text-red-600">Eliminar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-4 text-center text-gray-500">No hay estudiantes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $students->links() }}</div>
            </div>
        </div>
    </div>

    <!-- MODAL DE IMPORTACIÓN MASIVA (NUEVO) -->
    <x-dialog-modal wire:model="isImportModalOpen" maxWidth="lg">
        <x-slot name="title">Importar Matrícula de Estudiantes (Planilla Maestra)</x-slot>
        <x-slot name="content">
            <div class="space-y-4">
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                El archivo debe ser un Excel (.xlsx) y la primera fila (Cabecera) debe contener exactamente los siguientes nombres de columna en minúscula:
                            </p>
                            <p class="text-xs font-mono bg-white p-2 mt-2 rounded border font-semibold text-gray-800 overflow-x-auto">
                                dni | nombres | apellidos | genero | cod_carrera | cod_plan | semestre | periodo | seccion | turno
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <x-label value="Seleccionar Archivo Excel" />
                    <input type="file" wire:model="file" accept=".xlsx, .xls" class="mt-2 block w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-indigo-50 file:text-indigo-700
                        hover:file:bg-indigo-100
                    "/>
                    <x-input-error for="file" class="mt-2" />
                </div>

                <!-- Indicador de Carga -->
                <div wire:loading wire:target="file" class="text-sm text-blue-600 font-medium">
                    Procesando documento temporal...
                </div>
            </div>
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeImportModal">Cancelar</x-secondary-button>
            <x-button class="ml-3 bg-green-600 hover:bg-green-700" wire:click="import" wire:loading.attr="disabled" wire:target="import, file">
                <span wire:loading.remove wire:target="import">Subir y Matricular</span>
                <span wire:loading wire:target="import">Procesando Importación...</span>
            </x-button>
        </x-slot>
    </x-dialog-modal>

    <!-- MODAL DE CREAR / EDITAR (EXISTENTE) -->
    <x-dialog-modal wire:model="isModalOpen" maxWidth="2xl">
        <x-slot name="title">{{ $editingStudent ? 'Editar Estudiante' : 'Nuevo Estudiante' }}</x-slot>
        <x-slot name="content">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <h3 class="text-lg font-medium border-b">Datos Personales</h3>
                    
                    <div>
                        <x-label value="Buscar por DNI" />
                        <div class="flex gap-2">
                            <x-input wire:model="searchDni" type="text" class="w-full" maxlength="8" placeholder="Ingrese DNI" />
                            <x-button type="button" wire:click="searchPersonByDni" wire:loading.attr="disabled">
                                <svg wire:loading.remove wire:target="searchPersonByDni" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                <span wire:loading wire:target="searchPersonByDni">...</span>
                            </x-button>
                        </div>
                        <x-input-error for="searchDni" />
                        <x-input-error for="document_number" />
                    </div>

                    <div>
                        <x-label value="Nombres" />
                        <x-input wire:model="name" type="text" class="w-full" />
                        <x-input-error for="name" />
                    </div>
                    <div>
                        <x-label value="Apellidos" />
                        <x-input wire:model="lastname" type="text" class="w-full" />
                        <x-input-error for="lastname" />
                    </div>

                    <div><x-label value="Email" /><x-input wire:model="email" type="email" class="w-full" /><x-input-error for="email" /></div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <div><x-label value="Celular" /><x-input wire:model="phone" class="w-full" /></div>
                        <div>
                            <x-label value="Género" />
                            <select wire:model="gender" class="w-full border-gray-300 rounded-md">
                                <option value="masculino">Masculino</option>
                                <option value="femenino">Femenino</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-lg font-medium border-b">Datos Académicos</h3>
                    <div>
                        <x-label value="Código Estudiante" />
                        <x-input wire:model="code" class="w-full uppercase" />
                        <x-input-error for="code" />
                    </div>
                    <div>
                        <x-label value="Carrera" />
                        <select wire:model.live="career_id" class="w-full border-gray-300 rounded-md">
                            <option value="">Seleccione...</option>
                            @foreach($careers as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="career_id" />
                    </div>
                    <div>
                        <x-label value="Plan de Estudios" />
                        <select wire:model="study_plan_id" class="w-full border-gray-300 rounded-md">
                            <option value="">Seleccione Plan...</option>
                            @foreach($studyPlans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->code }})</option>
                            @endforeach
                        </select>
                        <x-input-error for="study_plan_id" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <x-label value="Semestre Actual" />
                            <x-input wire:model="current_semester" type="number" min="1" max="10" class="w-full" />
                        </div>
                        <div>
                            <x-label value="Fecha Admisión" />
                            <x-input wire:model="admission_date" type="date" class="w-full" />
                        </div>
                    </div>
                    <div>
                        <x-label value="Situación Académica" />
                        <select wire:model="academic_status" class="w-full border-gray-300 rounded-md">
                            <option value="regular">Regular</option>
                            <option value="irregular">Irregular (Repitente)</option>
                            <option value="enrollment_reserved">Reserva de Matrícula</option>
                            <option value="withdrawn">Retirado</option>
                            <option value="graduated">Egresado</option>
                        </select>
                    </div>
                </div>
            </div>
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeModal">Cancelar</x-secondary-button>
            <x-button class="ml-3" wire:click="save" wire:loading.attr="disabled">Guardar</x-button>
        </x-slot>
    </x-dialog-modal>

    @script
    <script>
        Livewire.on('swal:confirm', (event) => {
            let data = event;
            if (Array.isArray(event) && event.length > 0) {
                data = event[0];
            }
            if (data.detail) {
                data = data.detail;
            }

            if (!data.method || !data.id) {
                console.error('Error: Faltan datos para el borrado (id o method)', data);
                return;
            }

            Swal.fire({
                title: data.title || '¿Estás seguro?',
                text: data.text || "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch(data.method, { id: data.id });
                }
            });
        });

        Livewire.on('swal', (event) => {
            let data = event;
            if (Array.isArray(event)) data = event[0];
            
            Swal.fire({
                icon: data.icon,
                title: data.title,
                text: data.text,
                showConfirmButton: false,
                timer: 3000,
                toast: true,
                position: 'top-end'
            });
        });
    </script>
    @endscript
</div>