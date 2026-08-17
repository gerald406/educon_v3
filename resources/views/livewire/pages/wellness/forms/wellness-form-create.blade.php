<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Nuevo Formulario de Bienestar Estudiantil
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <form wire:submit.prevent="save" class="space-y-8">
                        <!-- ======== DATOS GENERALES ======== -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="form.code" class="block text-sm font-medium text-gray-700">Código *</label>
                                <input type="text" wire:model="form.code" id="form.code"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                @error('form.code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="form.name" class="block text-sm font-medium text-gray-700">Nombre *</label>
                                <input type="text" wire:model="form.name" id="form.name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                @error('form.name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="form.description" class="block text-sm font-medium text-gray-700">Descripción</label>
                                <textarea wire:model="form.description" id="form.description" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"></textarea>
                            </div>
                            <div>
                                <label for="form.form_type" class="block text-sm font-medium text-gray-700">Tipo *</label>
                                <select wire:model="form.form_type" id="form.form_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
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
                                <select wire:model="form.target_audience" id="form.target_audience"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <option value="all">Todos</option>
                                    <option value="students">Solo estudiantes</option>
                                    <option value="specific_careers">Carreras específicas</option>
                                    <option value="specific_periods">Periodos específicos</option>
                                </select>
                                @error('form.target_audience') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="form.starts_at" class="block text-sm font-medium text-gray-700">Fecha de inicio</label>
                                <input type="datetime-local" wire:model="form.starts_at" id="form.starts_at"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </div>
                            <div>
                                <label for="form.ends_at" class="block text-sm font-medium text-gray-700">Fecha de fin</label>
                                <input type="datetime-local" wire:model="form.ends_at" id="form.ends_at"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                @error('form.ends_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-wrap items-center gap-4">
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" wire:model="form.is_active"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="text-sm text-gray-700">Activo</span>
                                </label>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" wire:model="form.is_mandatory"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="text-sm text-gray-700">Obligatorio</span>
                                </label>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" wire:model="form.allow_multiple_responses"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="text-sm text-gray-700">Permitir múltiples respuestas</span>
                                </label>
                            </div>
                        </div>

                        <!-- ======== ESTRUCTURA DEL FORMULARIO ======== -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Estructura del Formulario</h3>
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
                            @foreach ($sections as $sectionIndex => $section)
                                <div class="border rounded-md p-4 mb-4 bg-gray-50">
                                    <div class="flex justify-between items-center mb-2">
                                        <h4 class="font-medium text-gray-800">Sección {{ $sectionIndex + 1 }}</h4>
                                        <button type="button" wire:click="removeSection({{ $sectionIndex }})"
                                            class="text-red-600 hover:text-red-800 text-sm">Eliminar</button>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Nombre de la sección</label>
                                            <input type="text" wire:model="sections.{{ $sectionIndex }}.name"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Orden</label>
                                            <input type="number" wire:model="sections.{{ $sectionIndex }}.sort_order"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Descripción</label>
                                            <input type="text" wire:model="sections.{{ $sectionIndex }}.description"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        </div>
                                        <div>
                                            <label class="flex items-center space-x-2">
                                                <input type="checkbox" wire:model="sections.{{ $sectionIndex }}.is_collapsible"
                                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="text-sm text-gray-700">Colapsable</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Preguntas de la sección -->
                                    <div class="mt-4">
                                        <h5 class="text-sm font-medium text-gray-700 mb-2">Preguntas</h5>

                                        @foreach ($section['questions'] as $questionIndex => $question)
                                            @include('livewire.pages.wellness.forms.partials.question-fields', [
                                                'sectionIndex' => $sectionIndex,
                                                'questionIndex' => $questionIndex,
                                                'question' => $question,
                                                'questionTypeMeta' => $questionTypeMeta,
                                            ])
                                        @endforeach

                                        <button type="button" wire:click="addQuestion({{ $sectionIndex }})"
                                            class="mt-2 inline-flex items-center px-3 py-1 bg-green-100 border border-transparent rounded-md font-semibold text-xs text-green-700 uppercase tracking-widest hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            Agregar pregunta
                                        </button>
                                    </div>
                                </div>
                            @endforeach

                            <button type="button" wire:click="addSection"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Agregar sección
                            </button>
                        </div>

                        <!-- ======== BOTONES ======== -->
                        <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                            <a href="{{ route('wellness.forms.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Cancelar
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>