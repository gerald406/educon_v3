<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Editar Formulario: {{ $form['name'] ?? 'Sin nombre' }}
            </h2>
            <a href="{{ route('wellness.forms.index') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                &larr; Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <form wire:submit.prevent="save" class="space-y-8">
                        <!-- ======== DATOS GENERALES (Siempre editables) ======== -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Datos Generales</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="form.code" class="block text-sm font-medium text-gray-700">Código *</label>
                                    <input type="text" wire:model="form.code" id="form.code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    @error('form.code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="form.name" class="block text-sm font-medium text-gray-700">Nombre *</label>
                                    <input type="text" wire:model="form.name" id="form.name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    @error('form.name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label for="form.description" class="block text-sm font-medium text-gray-700">Descripción</label>
                                    <textarea wire:model="form.description" id="form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"></textarea>
                                </div>
                                <div>
                                    <label for="form.form_type" class="block text-sm font-medium text-gray-700">Tipo *</label>
                                    <select wire:model="form.form_type" id="form.form_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        <option value="encuesta">Encuesta</option>
                                        <option value="cuestionario">Cuestionario</option>
                                        <option value="ficha_socioeconomica">Ficha Socioeconómica</option>
                                        <option value="diagnostico">Diagnóstico</option>
                                        <option value="evaluacion">Evaluación</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                    @error('form.form_type') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="form.target_audience" class="block text-sm font-medium text-gray-700">Audiencia objetivo *</label>
                                    <select wire:model="form.target_audience" id="form.target_audience" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        <option value="all">Todos</option>
                                        <option value="students">Solo estudiantes</option>
                                        <option value="specific_careers">Carreras específicas</option>
                                        <option value="specific_periods">Periodos específicos</option>
                                    </select>
                                    @error('form.target_audience') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="form.starts_at" class="block text-sm font-medium text-gray-700">Fecha de inicio</label>
                                    <input type="datetime-local" wire:model="form.starts_at" id="form.starts_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    @error('form.starts_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="form.ends_at" class="block text-sm font-medium text-gray-700">Fecha de fin</label>
                                    <input type="datetime-local" wire:model="form.ends_at" id="form.ends_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    @error('form.ends_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div class="md:col-span-2 flex flex-wrap gap-6">
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" wire:model="form.is_active" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        <span class="text-sm text-gray-700">Activo</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" wire:model="form.is_mandatory" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        <span class="text-sm text-gray-700">Obligatorio</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" wire:model="form.allow_multiple_responses" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        <span class="text-sm text-gray-700">Permitir múltiples respuestas</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- ======== ESTRUCTURA DEL FORMULARIO ======== -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Estructura del Formulario</h3>
                            
                            @if($hasResponses)
                                <!-- ALERTA DE BLOQUEO DE INTEGRIDAD -->
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.5-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700">
                                                <strong>Estructura bloqueada:</strong> Este formulario ya cuenta con respuestas registradas de estudiantes. Para garantizar la integridad de los datos estadísticos e históricos, no es posible agregar, editar o eliminar secciones, preguntas ni opciones. Solo puedes modificar los Datos Generales superiores.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vista de solo lectura (Opcional, para mostrar lo que hay) -->
                                <div class="opacity-60 pointer-events-none">
                                    @foreach ($sections as $sectionIndex => $section)
                                        <div class="border rounded-md p-4 mb-4 bg-gray-50">
                                            <h4 class="font-medium text-gray-800 mb-2">Sección {{ $sectionIndex + 1 }}: {{ $section['name'] }}</h4>
                                            <p class="text-sm text-gray-600 mb-4">{{ $section['description'] }}</p>
                                            
                                            <div class="pl-4 border-l-2 border-gray-300 space-y-3">
                                                @foreach ($section['questions'] as $q)
                                                    <div>
                                                        <span class="font-medium text-sm text-gray-700">{{ $q['question_text'] }}</span>
                                                        <span class="text-xs text-gray-500">({{ $q['question_type'] }})</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <!-- CONSTRUCTOR DE FORMULARIO ACTIVO -->
                                <div class="flex justify-end mb-4">
                                    <button type="button" wire:click="addSection" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Agregar sección
                                    </button>
                                </div>

                                @php
                                    $questionTypeMeta = [
                                        'text_short' => ['label' => 'Texto corto', 'icon' => '✎'],
                                        'text_long' => ['label' => 'Texto largo', 'icon' => '≣'],
                                        'numeric' => ['label' => 'Numérico', 'icon' => '#'],
                                        'date' => ['label' => 'Fecha', 'icon' => '📅'],
                                        'yes_no' => ['label' => 'Sí / No', 'icon' => '✓'],
                                        'true_false' => ['label' => 'V / F', 'icon' => '⚖'],
                                        'single_choice' => ['label' => 'Única', 'icon' => '◉'],
                                        'multiple_choice' => ['label' => 'Múltiple', 'icon' => '☑'],
                                        'dropdown' => ['label' => 'Lista', 'icon' => '▾'],
                                        'file_upload' => ['label' => 'Archivo', 'icon' => '↑'],
                                        'rating' => ['label' => 'Valoración', 'icon' => '★'],
                                    ];
                                @endphp

                                @forelse ($sections as $sectionIndex => $section)
                                    <div class="border rounded-md p-4 mb-4 bg-gray-50 shadow-sm">
                                        <div class="flex justify-between items-start mb-3 border-b pb-2">
                                            <h4 class="font-medium text-gray-800">Sección {{ $sectionIndex + 1 }}</h4>
                                            <div class="flex space-x-2">
                                                <button type="button" wire:click="addQuestion({{ $sectionIndex }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Agregar pregunta</button>
                                                <button type="button" wire:click="removeSection({{ $sectionIndex }})" class="text-red-600 hover:text-red-800 text-sm font-medium">Eliminar sección</button>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Nombre de la sección</label>
                                                <input type="text" wire:model="sections.{{ $sectionIndex }}.name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Orden</label>
                                                <input type="number" wire:model="sections.{{ $sectionIndex }}.sort_order" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Descripción</label>
                                                <input type="text" wire:model="sections.{{ $sectionIndex }}.description" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                            </div>
                                            <div>
                                                <label class="flex items-center space-x-2">
                                                    <input type="checkbox" wire:model="sections.{{ $sectionIndex }}.is_collapsible" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                    <span class="text-sm text-gray-700">Permitir que la sección sea colapsable</span>
                                                </label>
                                            </div>
                                        </div>

                                        @forelse ($section['questions'] as $questionIndex => $question)
                                            @include('livewire.pages.wellness.forms.partials.question-fields', [
                                                'sectionIndex' => $sectionIndex,
                                                'questionIndex' => $questionIndex,
                                                'question' => $question,
                                                'questionTypeMeta' => $questionTypeMeta,
                                            ])
                                        @empty
                                            <div class="text-center py-4 text-gray-500 bg-white border border-dashed rounded">
                                                No hay preguntas en esta sección.
                                                <button type="button" wire:click="addQuestion({{ $sectionIndex }})" class="ml-2 text-blue-600 hover:text-blue-800 underline">Agregar primera pregunta</button>
                                            </div>
                                        @endforelse
                                    </div>
                                @empty
                                    <div class="text-center py-8 border-2 border-dashed border-gray-300 rounded-md bg-gray-50">
                                        <p class="text-gray-500">No hay secciones creadas en el formulario.</p>
                                        <button type="button" wire:click="addSection" class="mt-2 inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            Agregar primera sección
                                        </button>
                                    </div>
                                @endforelse
                            @endif
                        </div>

                        <!-- ======== BOTONES ======== -->
                        <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                            <a href="{{ route('wellness.forms.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Cancelar
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Actualizar Formulario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>