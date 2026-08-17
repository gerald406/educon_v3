<div class="border-l-4 border-blue-200 pl-4 mb-3 bg-white p-3 rounded" wire:key="q-{{ $sectionIndex }}-{{ $questionIndex }}">
    <div class="flex justify-between items-start">
        <span class="font-medium text-gray-700">Pregunta {{ $questionIndex + 1 }}</span>
        <button type="button" wire:click="removeQuestion({{ $sectionIndex }}, {{ $questionIndex }})"
            class="text-red-600 hover:text-red-800 text-sm">Eliminar</button>
    </div>

    <div class="mt-2">
        <label class="block text-sm font-medium text-gray-700">Texto de la pregunta</label>
        <input type="text" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.question_text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
            placeholder="Escribe el texto de la pregunta">
    </div>

    <!-- Selector de tipo de respuesta (chips, mobile first) -->
    <div class="mt-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de respuesta</label>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
            @foreach ($questionTypeMeta as $typeValue => $typeInfo)
                @php $isActive = $question['question_type'] === $typeValue; @endphp
                <button type="button"
                    wire:click="$set('sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.question_type', '{{ $typeValue }}')"
                    class="flex flex-col items-center justify-center p-2 rounded-md border text-center transition
                        {{ $isActive ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50' }}">
                    <span class="text-base leading-none">{{ $typeInfo['icon'] }}</span>
                    <span class="text-[11px] mt-1 leading-tight">{{ $typeInfo['label'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
        <div>
            <label class="block text-sm font-medium text-gray-700">Orden</label>
            <input type="number" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.sort_order"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
        </div>
        <div class="flex items-end">
            <label class="flex items-center space-x-2 mb-1">
                <input type="checkbox" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.is_required"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                <span class="text-sm text-gray-700">Obligatoria</span>
            </label>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Texto de ayuda</label>
            <input type="text" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.help_text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
        </div>

        @if (!in_array($question['question_type'], ['single_choice', 'multiple_choice', 'dropdown', 'yes_no', 'true_false', 'rating']))
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Valor por defecto</label>
                <input type="text" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.default_value"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
        @endif
    </div>

    <!-- Panel dinámico de configuración de respuesta, según el tipo elegido -->
    <div class="mt-3 bg-gray-50 rounded-md p-3">
        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Vista previa de respuesta</h6>

        @switch($question['question_type'])
            @case('single_choice')
            @case('multiple_choice')
            @case('dropdown')
                <div>
                    @foreach ($question['options'] as $optionIndex => $option)
                        <div class="flex items-center space-x-2 mt-1">
                            <input type="text" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.options.{{ $optionIndex }}.option_value"
                                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                placeholder="Opción {{ $optionIndex + 1 }}">
                            <input type="number" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.options.{{ $optionIndex }}.sort_order"
                                class="w-16 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                placeholder="Orden">
                            <button type="button" wire:click="removeOption({{ $sectionIndex }}, {{ $questionIndex }}, {{ $optionIndex }})"
                                class="text-red-600 hover:text-red-800">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endforeach
                    <button type="button" wire:click="addOption({{ $sectionIndex }}, {{ $questionIndex }})"
                        class="mt-2 inline-flex items-center px-3 py-1 bg-blue-100 border border-transparent rounded-md font-semibold text-xs text-blue-700 uppercase tracking-widest hover:bg-blue-200">
                        Agregar opción
                    </button>
                </div>
                @break

            @case('yes_no')
                <div class="flex gap-2">
                    <span class="px-3 py-1 rounded-full bg-white border border-gray-300 text-sm text-gray-600">Sí</span>
                    <span class="px-3 py-1 rounded-full bg-white border border-gray-300 text-sm text-gray-600">No</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Opciones fijas, no editables.</p>
                @break

            @case('true_false')
                <div class="flex gap-2">
                    <span class="px-3 py-1 rounded-full bg-white border border-gray-300 text-sm text-gray-600">Verdadero</span>
                    <span class="px-3 py-1 rounded-full bg-white border border-gray-300 text-sm text-gray-600">Falso</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Opciones fijas, no editables.</p>
                @break

            @case('rating')
                <div class="flex items-center gap-1 mb-3 text-blue-400 text-xl leading-none">
                    <span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Escala máxima</label>
                    <select wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.validation_rules.max"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="5">5</option>
                        <option value="10">10</option>
                    </select>
                </div>
                @break

            @case('numeric')
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-600">Valor mínimo</label>
                        <input type="number" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.validation_rules.min"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Valor máximo</label>
                        <input type="number" wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.validation_rules.max"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                </div>
                @break

            @case('file_upload')
                <p class="text-sm text-gray-500">Se permitirá cargar archivos (PDF, JPG, PNG) al momento de responder.</p>
                @break

            @default
                <p class="text-sm text-gray-400">Esta pregunta no requiere configuración adicional.</p>
        @endswitch
    </div>
</div>