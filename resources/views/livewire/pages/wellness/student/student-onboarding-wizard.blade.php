<div>
    <div class="min-h-screen bg-gray-50 py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            
            <!-- Encabezado del Wizard -->
            <div class="text-center mb-10">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                    ¡Bienvenido a tu Portal Estudiantil!
                </h2>
                <p class="mt-4 text-lg text-gray-600">
                    Para habilitar tu acceso completo al sistema, por favor completa tu información personal y ficha socioeconómica.
                </p>
            </div>

            <!-- Indicador de Progreso -->
            @if($surveyForm)
            <div class="mb-8">
                <div class="flex items-center justify-center space-x-4">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $step === 1 ? 'border-blue-600 bg-blue-600 text-white' : 'border-green-500 bg-green-500 text-white' }} font-bold transition-all">
                            @if($step === 2)
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            @else
                                1
                            @endif
                        </div>
                        <span class="ml-3 text-sm font-medium {{ $step === 1 ? 'text-blue-600' : 'text-green-600' }}">Datos Demográficos</span>
                    </div>
                    <div class="w-12 border-t-2 {{ $step === 2 ? 'border-green-500' : 'border-gray-300' }}"></div>
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $step === 2 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-500' }} font-bold transition-all">
                            2
                        </div>
                        <span class="ml-3 text-sm font-medium {{ $step === 2 ? 'text-blue-600' : 'text-gray-500' }}">Ficha Socioeconómica</span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Contenedor Principal de las Tarjetas -->
            <div class="bg-white shadow overflow-hidden sm:rounded-xl">
                
                <!-- ========================================== -->
                <!-- PASO 1: DATOS DEMOGRÁFICOS                 -->
                <!-- ========================================== -->
                @if($step === 1)
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-4 mb-6">Información Personal Obligatoria</h3>

                    <!-- Alerta para estudiantes pre-matriculados -->
                    @if ($isExistingStudent)
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700 font-medium">
                                        Tus datos académicos y matrícula ya han sido asignados por la administración. Por favor, actualiza únicamente tu información de contacto y procedencia.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                        
                        <!-- Si el alumno fuera totalmente nuevo (registro manual), vería esto habilitado -->
                        @if (!$isExistingStudent)
                            <div>
                                <x-label value="Programa de Estudios *" />
                                <select wire:model.live="profile.career_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Seleccione...</option>
                                    @foreach($careers as $career)
                                        <option value="{{ $career->id }}">{{ $career->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error for="profile.career_id" class="mt-1" />
                            </div>

                            <div>
                                <x-label value="Plan de Estudios *" />
                                <select wire:model="profile.study_plan_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Seleccione...</option>
                                    @foreach($studyPlans as $plan)
                                        <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->code }})</option>
                                    @endforeach
                                </select>
                                <x-input-error for="profile.study_plan_id" class="mt-1" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-label value="Código Institucional *" />
                                <x-input wire:model="profile.code" type="text" class="mt-1 w-full sm:w-1/2" />
                                <x-input-error for="profile.code" class="mt-1" />
                            </div>
                        @else
                            <!-- Para alumnos importados, mostramos sus datos como solo lectura -->
                            @php
                                $assignedPlan = \App\Models\StudyPlan::find($profile['study_plan_id']);
                            @endphp
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-md border border-gray-200">
                                <div>
                                    <x-label value="Programa de Estudios Asignado" class="text-gray-500" />
                                    <div class="mt-1 font-semibold text-gray-800">
                                        {{ \App\Models\Career::find($profile['career_id'])->name ?? 'No asignado' }}
                                    </div>
                                </div>
                                <div>
                                    <x-label value="Plan de Estudios" class="text-gray-500" />
                                    <div class="mt-1 font-semibold text-gray-800">
                                        {{ $assignedPlan ? $assignedPlan->name . ' (' . $assignedPlan->code . ')' : 'No asignado' }}
                                    </div>
                                </div>
                                <div>
                                    <x-label value="Código Institucional" class="text-gray-500" />
                                    <div class="mt-1 font-semibold text-gray-800">{{ $profile['code'] }}</div>
                                </div>
                            </div>
                        @endif

                        <!-- Contacto -->
                        <div>
                            <x-label value="Número de Celular *" />
                            <x-input wire:model="profile.phone" type="tel" class="mt-1 w-full" placeholder="Ej: 987654321" />
                            <x-input-error for="profile.phone" class="mt-1" />
                        </div>

                        <div>
                            <x-label value="Dirección Actual *" />
                            <x-input wire:model="profile.address" type="text" class="mt-1 w-full" placeholder="Av. Principal 123" />
                            <x-input-error for="profile.address" class="mt-1" />
                        </div>

                        <!-- Nacimiento y Género -->
                        <div>
                            <x-label value="Fecha de Nacimiento *" />
                            <x-input wire:model="profile.birthday" type="date" class="mt-1 w-full" />
                            <x-input-error for="profile.birthday" class="mt-1" />
                        </div>

                        <div>
                            <x-label value="Género *" />
                            <select wire:model="profile.gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Seleccione...</option>
                                <option value="masculino">Masculino</option>
                                <option value="femenino">Femenino</option>
                            </select>
                            <x-input-error for="profile.gender" class="mt-1" />
                        </div>

                        <!-- Buscador Predictivo Ubigeo -->
                        <div class="sm:col-span-2 bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <h4 class="text-sm font-bold text-gray-700 mb-4">Lugar de Nacimiento (Ubigeo) *</h4>
                            <div class="relative">
                                <x-input type="text" class="w-full" wire:model.live.debounce.300ms="ubigeoSearch" placeholder="Escriba el nombre del distrito o provincia para buscar..." />
                                
                                @if(!empty($ubigeoResults))
                                    <ul class="absolute z-50 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 max-h-60 overflow-y-auto">
                                        @foreach($ubigeoResults as $loc)
                                            <li class="p-2 hover:bg-gray-100 cursor-pointer text-sm border-b" 
                                                wire:click="selectUbigeo('{{ $loc['iddist'] }}', '{{ $loc['nombdep'] }} / {{ $loc['nombprov'] }} / {{ $loc['nombdist'] }}')">
                                                <span class="font-bold">{{ $loc['nombdist'] }}</span> 
                                                <span class="text-xs text-gray-500">({{ $loc['nombprov'] }}, {{ $loc['nombdep'] }})</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                
                                @if($selectedUbigeoName)
                                    <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded text-sm text-green-800 flex items-center justify-between shadow-sm">
                                        <span><strong>Seleccionado:</strong> {{ $selectedUbigeoName }}</span>
                                        <button type="button" wire:click="$set('selectedUbigeoName', ''); $set('profile.ubigeo_birth_id', '')" class="text-red-500 hover:text-red-700 font-bold ml-4" title="Limpiar y buscar de nuevo">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                @endif
                                <x-input-error for="profile.ubigeo_birth_id" class="mt-2" />
                            </div>
                        </div>

                        <!-- Buscador Predictivo Colegio -->
                        <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2 relative">
                                <x-label value="Colegio de Procedencia *" />
                                <x-input type="text" class="w-full mt-1" wire:model.live.debounce.300ms="schoolSearch" placeholder="Escriba el nombre de su colegio..." />
                                
                                @if(!empty($schoolResults))
                                    <ul class="absolute z-50 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 max-h-60 overflow-y-auto">
                                        @foreach($schoolResults as $school)
                                            <li class="p-2 hover:bg-gray-100 cursor-pointer text-sm border-b transition-colors" 
                                                wire:click="selectSchool({{ $school->id }}, '{{ $school->name }}')">
                                                <span class="font-bold block">{{ $school->name }}</span>
                                                <span class="text-xs text-gray-600 block mt-1">
                                                    <span class="font-semibold text-blue-600">{{ $school['d_niv_mod'] ?? 'N/A' }}</span>
                                                    <span class="mx-1">|</span>
                                                    {{ $school['location']['nombdist'] ?? '' }} - {{ $school['location']['nombdep'] ?? '' }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                
                                @if($selectedSchoolName)
                                    <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded text-sm text-green-800 flex items-center justify-between shadow-sm">
                                        <span><strong>Seleccionado:</strong> {{ $selectedSchoolName }}</span>
                                        <button type="button" wire:click="$set('selectedSchoolName', ''); $set('profile.origin_school_id', '')" class="text-red-500 hover:text-red-700 font-bold ml-4" title="Limpiar y buscar de nuevo">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                @endif
                                <x-input-error for="profile.origin_school_id" class="mt-1" />
                            </div>
                            
                            <div>
                                <x-label value="Año de Egreso *" />
                                <x-input wire:model="profile.school_graduation_year" type="number" class="mt-1 w-full" placeholder="Ej: 2024" />
                                <x-input-error for="profile.school_graduation_year" class="mt-1" />
                            </div>
                        </div>

                    </div>
                </div>
                
                <!-- Footer Paso 1 -->
                <div class="px-4 py-3 bg-gray-50 text-right sm:px-6 border-t border-gray-200">
                    <x-button wire:click="saveProfileAndContinue" wire:loading.attr="disabled" class="bg-blue-600 hover:bg-blue-700">
                        <span wire:loading.remove wire:target="saveProfileAndContinue">Siguiente Paso &rarr;</span>
                        <span wire:loading wire:target="saveProfileAndContinue">Guardando...</span>
                    </x-button>
                </div>
                @endif

                <!-- ========================================== -->
                <!-- PASO 2: FICHA SOCIOECONÓMICA               -->
                <!-- ========================================== -->
                @if($step === 2 && $surveyForm)
                <div class="px-4 py-5 sm:p-6">
                    <div class="text-center mb-6 border-b pb-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ $surveyForm->name }}</h3>
                        @if($surveyForm->description)
                            <p class="mt-2 text-sm text-gray-600">{{ $surveyForm->description }}</p>
                        @endif
                    </div>

                    <div class="space-y-8">
                        @foreach ($surveyForm->sections as $section)
                            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                                <div class="bg-gray-100 px-4 py-3 border-b border-gray-200">
                                    <h4 class="text-lg font-semibold text-gray-800">{{ $section->name }}</h4>
                                    @if($section->description)
                                        <p class="text-xs text-gray-500 mt-1">{{ $section->description }}</p>
                                    @endif
                                </div>
                                <div class="p-4 space-y-6">
                                    @foreach ($section->questions as $question)
                                        <div class="flex flex-col">
                                            <label class="text-sm font-medium text-gray-900 mb-1">
                                                {{ $question->question_text }}
                                                @if($question->is_required) <span class="text-red-500">*</span> @endif
                                            </label>
                                            
                                            @if($question->help_text)
                                                <p class="text-xs text-gray-500 mb-2">{{ $question->help_text }}</p>
                                            @endif

                                            <div class="mt-1">
                                                @if($question->question_type === 'text_short')
                                                    <x-input type="text" wire:model="answers.{{ $question->id }}" class="w-full" />
                                                
                                                @elseif($question->question_type === 'text_long')
                                                    <textarea wire:model="answers.{{ $question->id }}" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                                
                                                @elseif($question->question_type === 'numeric')
                                                    <x-input type="number" wire:model="answers.{{ $question->id }}" class="w-full sm:w-1/3" />
                                                
                                                @elseif($question->question_type === 'date')
                                                    <x-input type="date" wire:model="answers.{{ $question->id }}" class="w-full sm:w-1/3" />
                                                
                                                @elseif($question->question_type === 'yes_no' || $question->question_type === 'true_false')
                                                    <div class="flex space-x-6 mt-2">
                                                        <label class="flex items-center cursor-pointer">
                                                            <input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $question->question_type === 'yes_no' ? 'Sí' : 'Verdadero' }}" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                            <span class="ml-2 text-sm text-gray-700">{{ $question->question_type === 'yes_no' ? 'Sí' : 'Verdadero' }}</span>
                                                        </label>
                                                        <label class="flex items-center cursor-pointer">
                                                            <input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $question->question_type === 'yes_no' ? 'No' : 'Falso' }}" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                            <span class="ml-2 text-sm text-gray-700">{{ $question->question_type === 'yes_no' ? 'No' : 'Falso' }}</span>
                                                        </label>
                                                    </div>

                                                @elseif($question->question_type === 'single_choice')
                                                    <div class="space-y-2 mt-2">
                                                        @foreach($question->options as $option)
                                                            <label class="flex items-center cursor-pointer p-2 hover:bg-gray-50 rounded transition-colors">
                                                                <input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $option->id }}" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                                <span class="ml-3 text-sm text-gray-700">{{ $option->option_value }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>

                                                @elseif($question->question_type === 'multiple_choice')
                                                    <div class="space-y-2 mt-2">
                                                        @foreach($question->options as $option)
                                                            <label class="flex items-center cursor-pointer p-2 hover:bg-gray-50 rounded transition-colors">
                                                                <input type="checkbox" wire:model="answers.{{ $question->id }}.{{ $option->id }}" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                <span class="ml-3 text-sm text-gray-700">{{ $option->option_value }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>

                                                @elseif($question->question_type === 'dropdown')
                                                    <select wire:model="answers.{{ $question->id }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                        <option value="">Seleccione una opción...</option>
                                                        @foreach($question->options as $option)
                                                            <option value="{{ $option->id }}">{{ $option->option_value }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </div>
                                            <x-input-error for="answers.{{ $question->id }}" class="mt-1" />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Footer Paso 2 -->
                <div class="px-4 py-4 bg-gray-50 sm:px-6 flex justify-between items-center border-t border-gray-200">
                    <span class="text-xs text-gray-500 hidden md:block">Por favor, verifica tus respuestas antes de enviar.</span>
                    <x-button wire:click="submitSurvey" wire:loading.attr="disabled" class="w-full sm:w-auto bg-green-600 hover:bg-green-700">
                        <span wire:loading.remove wire:target="submitSurvey">Finalizar y Enviar &rarr;</span>
                        <span wire:loading wire:target="submitSurvey">Procesando envío...</span>
                    </x-button>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>