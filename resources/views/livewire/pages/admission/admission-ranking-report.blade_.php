<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reporte de Notas — Ranking de Postulantes</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Panel de Filtros --}}
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-4">Parámetros del Reporte</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <x-label value="Modalidad de Admisión *" />
                        <select wire:model.live="modalityId" class="w-full border-gray-300 rounded-md shadow-sm mt-1">
                            <option value="">Seleccione una modalidad...</option>
                            @foreach($modalities as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-label value="Programa de Estudios *" />
                        <select wire:model.live="careerId" class="w-full border-gray-300 rounded-md shadow-sm mt-1">
                            <option value="">Seleccione un programa...</option>
                            @foreach($careers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button wire:click="exportExcel"
                                @disabled(!$modalityId || !$careerId)
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-md shadow-sm transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Descargar Ranking Excel
                        </button>
                    </div>
                </div>
            </div>

            {{-- Preview del Ranking --}}
            @if($modalityId && $careerId)
                <div class="bg-white shadow-xl sm:rounded-lg p-6">

                    {{-- Cabecera con métricas --}}
                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-3">
                        <div>
                            <h3 class="text-base font-bold text-gray-800">Vista Previa del Ranking</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Postulantes evaluados ordenados por nota (mayor a menor)</p>
                        </div>
                        @if($vacancies > 0)
                            <div class="flex gap-3">
                                <div class="px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-lg text-center">
                                    <div class="text-xs text-emerald-600 font-medium">Vacantes</div>
                                    <div class="text-lg font-bold text-emerald-700">{{ $vacancies }}</div>
                                </div>
                                <div class="px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-lg text-center">
                                    <div class="text-xs text-blue-600 font-medium">Evaluados</div>
                                    <div class="text-lg font-bold text-blue-700">{{ count($rankingPreview) }}</div>
                                </div>
                                <div class="px-3 py-1.5 bg-indigo-50 border border-indigo-200 rounded-lg text-center">
                                    <div class="text-xs text-indigo-600 font-medium">Ingresaron</div>
                                    <div class="text-lg font-bold text-indigo-700">
                                        {{ collect($rankingPreview)->where('admitted', true)->count() }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if(empty($rankingPreview))
                        <div class="text-center py-12 text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-sm">No hay postulantes evaluados para esta combinación.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-800 text-white">
                                    <tr>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider w-12">N°</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">DNI</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Apellidos y Nombres</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider w-24">Nota</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider w-32">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($rankingPreview as $row)
                                        <tr class="{{ $row['admitted'] ? 'bg-emerald-50 hover:bg-emerald-100' : 'bg-red-50 hover:bg-red-100' }}">
                                            <td class="px-4 py-3 text-center">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                                    {{ $row['admitted'] ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600' }}">
                                                    {{ $row['position'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ $row['dni'] }}</td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['name'] }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="text-lg font-bold {{ $row['admitted'] ? 'text-emerald-700' : 'text-red-600' }}">
                                                    {{ $row['score'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($row['admitted'])
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                        ✓ INGRESÓ
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                                        ✗ NO INGRESÓ
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                        {{-- Línea divisoria después de la última vacante --}}
                                        @if($vacancies > 0 && $row['position'] === $vacancies && !$loop->last)
                                            <tr>
                                                <td colspan="5" class="px-4 py-1 bg-gray-100 text-center text-xs text-gray-500 font-semibold border-y-2 border-gray-400 border-dashed">
                                                    ── Límite de vacantes ({{ $vacancies }}) ──
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Total: {{ count($rankingPreview) }} postulante(s) evaluado(s)</p>
                    @endif
                </div>
            @endif

        </div>
    </div>
</div>
