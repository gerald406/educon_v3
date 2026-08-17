<div>
    <div class="space-y-4">
        @foreach ($questions as $question)
            <div class="border-l-4 border-blue-200 pl-4 mb-3 bg-white p-3 rounded">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <input type="text" wire:change="updateQuestion({{ $question['id'] }}, 'question_text', $event.target.value)"
                            value="{{ $question['question_text'] }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                            placeholder="Texto de la pregunta">
                    </div>
                    <button type="button" wire:click="deleteQuestion({{ $question['id'] }})"
                        class="ml-2 text-red-600 hover:text-red-800 text-sm">Eliminar</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select wire:change="updateQuestion({{ $question['id'] }}, 'question_type', $event.target.value)"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="text_short" {{ $question['question_type'] == 'text_short' ? 'selected' : '' }}>Texto corto</option>
                            <option value="text_long" {{ $question['question_type'] == 'text_long' ? 'selected' : '' }}>Texto largo</option>
                            <option value="numeric" {{ $question['question_type'] == 'numeric' ? 'selected' : '' }}>Numérico</option>
                            <option value="date" {{ $question['question_type'] == 'date' ? 'selected' : '' }}>Fecha</option>
                            <option value="yes_no" {{ $question['question_type'] == 'yes_no' ? 'selected' : '' }}>Sí / No</option>
                            <option value="true_false" {{ $question['question_type'] == 'true_false' ? 'selected' : '' }}>Verdadero / Falso</option>
                            <option value="single_choice" {{ $question['question_type'] == 'single_choice' ? 'selected' : '' }}>Selección única</option>
                            <option value="multiple_choice" {{ $question['question_type'] == 'multiple_choice' ? 'selected' : '' }}>Selección múltiple</option>
                            <option value="dropdown" {{ $question['question_type'] == 'dropdown' ? 'selected' : '' }}>Lista desplegable</option>
                            <option value="file_upload" {{ $question['question_type'] == 'file_upload' ? 'selected' : '' }}>Carga de archivos</option>
                            <option value="rating" {{ $question['question_type'] == 'rating' ? 'selected' : '' }}>Escala de valoración</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Orden</label>
                        <input type="number" wire:change="updateQuestion({{ $question['id'] }}, 'sort_order', $event.target.value)"
                            value="{{ $question['sort_order'] }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Texto de ayuda</label>
                        <input type="text" wire:change="updateQuestion({{ $question['id'] }}, 'help_text', $event.target.value)"
                            value="{{ $question['help_text'] }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" wire:change="updateQuestion({{ $question['id'] }}, 'is_required', $event.target.checked)"
                                {{ $question['is_required'] ? 'checked' : '' }}
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="text-sm text-gray-700">Obligatoria</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valor por defecto</label>
                        <input type="text" wire:change="updateQuestion({{ $question['id'] }}, 'default_value', $event.target.value)"
                            value="{{ $question['default_value'] }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Reglas de validación (JSON)</label>
                        <input type="text" wire:change="updateQuestion({{ $question['id'] }}, 'validation_rules', $event.target.value)"
                            value="{{ $question['validation_rules'] }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                            placeholder='{"min":1, "max":100}'>
                    </div>
                </div>

                <!-- Opciones -->
                @if (in_array($question['question_type'], ['single_choice', 'multiple_choice', 'dropdown']))
                    <div class="mt-3">
                        <h6 class="text-sm font-medium text-gray-700">Opciones</h6>
                        @foreach ($question['options'] as $option)
                            <div class="flex items-center space-x-2 mt-1">
                                <input type="text" wire:change="updateOption({{ $option['id'] }}, 'option_value', $event.target.value)"
                                    value="{{ $option['option_value'] }}"
                                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    placeholder="Opción">
                                <input type="number" wire:change="updateOption({{ $option['id'] }}, 'sort_order', $event.target.value)"
                                    value="{{ $option['sort_order'] }}"
                                    class="w-16 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    placeholder="Orden">
                                <button type="button" wire:click="deleteOption({{ $option['id'] }})"
                                    class="text-red-600 hover:text-red-800">X</button>
                            </div>
                        @endforeach
                        <button type="button" wire:click="addOption({{ $question['id'] }})"
                            class="mt-2 inline-flex items-center px-3 py-1 bg-blue-100 border border-transparent rounded-md font-semibold text-xs text-blue-700 uppercase tracking-widest hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Agregar opción
                        </button>
                    </div>
                @endif
            </div>
        @endforeach

        <button type="button" wire:click="addQuestion"
            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
            Agregar Pregunta
        </button>
    </div>
</div>