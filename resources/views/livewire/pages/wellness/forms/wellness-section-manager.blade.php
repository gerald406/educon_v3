<div>
    <div class="space-y-4">
        @foreach ($sections as $section)
            <div class="border rounded-md p-4 bg-gray-50">
                <div class="flex justify-between items-center mb-2">
                    <div class="flex-1">
                        <input type="text" wire:change="updateSection({{ $section['id'] }}, 'name', $event.target.value)"
                            value="{{ $section['name'] }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                            placeholder="Nombre de la sección">
                    </div>
                    <button type="button" wire:click="deleteSection({{ $section['id'] }})"
                        class="ml-2 text-red-600 hover:text-red-800 text-sm">Eliminar</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Orden</label>
                        <input type="number" wire:change="updateSection({{ $section['id'] }}, 'sort_order', $event.target.value)"
                            value="{{ $section['sort_order'] }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div>
                        <label class="flex items-center space-x-2 mt-1">
                            <input type="checkbox" wire:change="updateSection({{ $section['id'] }}, 'is_collapsible', $event.target.checked)"
                                {{ $section['is_collapsible'] ? 'checked' : '' }}
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="text-sm text-gray-700">Colapsable</span>
                        </label>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="block text-sm font-medium text-gray-700">Descripción</label>
                    <input type="text" wire:change="updateSection({{ $section['id'] }}, 'description', $event.target.value)"
                        value="{{ $section['description'] }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                        placeholder="Descripción de la sección">
                </div>
            </div>
        @endforeach

        <button type="button" wire:click="addSection"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
            Agregar Sección
        </button>
    </div>
</div>