{{-- [NUEVO] Sidebar del docente / coordinador (sin acceso administrativo).
     Mismo aspecto y botón de colapsar que el sidebar administrativo (CSS en layouts/app).
     Para agregar una opción: un <a> dentro del grupo que corresponda, con su @can si requiere
     permiso; para un grupo nuevo, copiar un bloque <div x-data="{ open: ... }"> completo. --}}
@php
    $user = Auth::user();
    $hasTeacherProfile = (bool) $user->teacher;

    $groupButton = 'w-full flex justify-between items-center px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white';
    $item = fn (bool $active) => 'block px-3 py-2 rounded-md text-sm font-medium '.($active ? 'text-white' : 'text-gray-400 hover:text-white');
    $chevron = 'M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z';

    $inSchedules = request()->routeIs('teacher.my-schedule*', 'teacher.non-teaching-schedule*');
    $inSyllabi = request()->routeIs('teacher.my-syllabi*', 'teacher.sessions.*', 'teacher.syllabus.*', 'academic-process.syllabus-approval');
@endphp

<div class="educon-sidebar h-screen w-64 bg-gray-800 text-white p-4">
    <div class="flex items-center mb-6">
        <a href="{{ route('dashboard') }}">
            <x-application-mark class="block h-10 w-auto" />
        </a>
        <span class="ms-3 text-xl font-semibold">Educon</span>

        <button type="button" class="educon-sidebar-toggle" @click="toggleSidebar()"
                title="Ocultar menú" aria-label="Ocultar menú lateral">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
    </div>

    <nav class="space-y-1">

        <a href="{{ route('dashboard') }}"
           class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 {{ request()->routeIs('dashboard') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
            <svg class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-7-4h10"></path></svg>
            Inicio
        </a>

        <div class="pt-4 pb-2">
            <span class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Panel del docente</span>
        </div>

        {{-- Horarios y programación (requiere ficha de docente) --}}
        @if ($hasTeacherProfile)
            <div class="mb-1" x-data="{ open: {{ $inSchedules ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="{{ $groupButton }}">
                    <span class="flex items-center">
                        <svg class="h-6 w-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Horarios
                    </span>
                    <svg :class="{'rotate-180': open}" class="h-5 w-5 transform transition-transform" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="{{ $chevron }}" clip-rule="evenodd"></path></svg>
                </button>
                <div x-show="open" class="mt-1 space-y-1 ml-6" x-collapse>
                    <a href="{{ route('teacher.my-schedule') }}" class="{{ $item(request()->routeIs('teacher.my-schedule*')) }}">Mi Horario</a>
                    <a href="{{ route('teacher.non-teaching-schedule') }}" class="{{ $item(request()->routeIs('teacher.non-teaching-schedule*')) }}">Horario No Lectivo</a>
                </div>
            </div>
        @endif

        {{-- Sílabos y sesiones de aprendizaje --}}
        @canany(['subir-silabo', 'aprobar-silabos'])
            <div class="mb-1" x-data="{ open: {{ $inSyllabi ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="{{ $groupButton }}">
                    <span class="flex items-center">
                        <svg class="h-6 w-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        Sílabos
                    </span>
                    <svg :class="{'rotate-180': open}" class="h-5 w-5 transform transition-transform" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="{{ $chevron }}" clip-rule="evenodd"></path></svg>
                </button>
                <div x-show="open" class="mt-1 space-y-1 ml-6" x-collapse>
                    @can('subir-silabo')
                        <a href="{{ route('teacher.my-syllabi') }}" class="{{ $item(request()->routeIs('teacher.my-syllabi*', 'teacher.sessions.*', 'teacher.syllabus.*')) }}">Mis Sílabos</a>
                    @endcan
                    @can('aprobar-silabos')
                        <a href="{{ route('academic-process.syllabus-approval') }}" class="{{ $item(request()->routeIs('academic-process.syllabus-approval')) }}">Aprobar Sílabos</a>
                    @endcan
                </div>
            </div>
        @endcanany

        {{-- Opciones aún no publicadas para el docente (estaban comentadas en la barra superior).
             Para habilitarlas, quitar este comentario Blade.

        @canany(['registrar-notas', 'registrar-asistencia', 'ver-reporte-asistencia', 'ver-reporte-acumulativo-asistencia'])
            <div class="mb-1" x-data="{ open: {{ request()->routeIs('evaluation.*', 'teacher.attendance-report', 'teacher.cumulative-attendance-report') ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="{{ $groupButton }}">
                    <span class="flex items-center">
                        <svg class="h-6 w-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                        Evaluación
                    </span>
                    <svg :class="{'rotate-180': open}" class="h-5 w-5 transform transition-transform" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="{{ $chevron }}" clip-rule="evenodd"></path></svg>
                </button>
                <div x-show="open" class="mt-1 space-y-1 ml-6" x-collapse>
                    @can('registrar-notas')
                        <a href="{{ route('evaluation.grades') }}" class="{{ $item(request()->routeIs('evaluation.grades')) }}">Registro de Notas</a>
                    @endcan
                    @can('registrar-asistencia')
                        <a href="{{ route('evaluation.attendances') }}" class="{{ $item(request()->routeIs('evaluation.attendances')) }}">Registro de Asistencia</a>
                    @endcan
                    @can('ver-reporte-asistencia')
                        <a href="{{ route('teacher.attendance-report') }}" class="{{ $item(request()->routeIs('teacher.attendance-report')) }}">Reporte de Asistencia</a>
                    @endcan
                    @can('ver-reporte-acumulativo-asistencia')
                        <a href="{{ route('teacher.cumulative-attendance-report') }}" class="{{ $item(request()->routeIs('teacher.cumulative-attendance-report')) }}">Asistencia Acumulada</a>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany(['gestionar-actividades', 'revisar-entregas'])
            <div class="mb-1" x-data="{ open: {{ request()->routeIs('teacher.activities', 'teacher.submissions') ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="{{ $groupButton }}">
                    <span class="flex items-center">
                        <svg class="h-6 w-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Actividades
                    </span>
                    <svg :class="{'rotate-180': open}" class="h-5 w-5 transform transition-transform" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="{{ $chevron }}" clip-rule="evenodd"></path></svg>
                </button>
                <div x-show="open" class="mt-1 space-y-1 ml-6" x-collapse>
                    @can('gestionar-actividades')
                        <a href="{{ route('teacher.activities') }}" class="{{ $item(request()->routeIs('teacher.activities')) }}">Actividades</a>
                    @endcan
                    @can('revisar-entregas')
                        <a href="{{ route('teacher.submissions') }}" class="{{ $item(request()->routeIs('teacher.submissions')) }}">Revisar Entregas</a>
                    @endcan
                </div>
            </div>
        @endcanany
        --}}
    </nav>
</div>
