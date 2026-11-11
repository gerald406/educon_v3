<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Educon') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- [NUEVO] Sidebar administrativo: barra de desplazamiento y colapso.
             CSS propio (no depende del build de Tailwind). El estado se aplica en <html>
             antes de pintar la página para que el sidebar no parpadee al cargar. --}}
        <script>
            try {
                if (localStorage.getItem('educon.sidebarCollapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        </script>
        <style>
            /* Desplazamiento: solo vertical, barra delgada y discreta */
            .educon-sidebar {
                overflow-x: hidden;
                overflow-y: auto;
                scrollbar-width: thin;
                scrollbar-color: #4b5563 transparent;
                scrollbar-gutter: stable;
            }
            .educon-sidebar::-webkit-scrollbar { width: 8px; height: 0; }
            .educon-sidebar::-webkit-scrollbar-track { background: transparent; }
            .educon-sidebar::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 9999px; border: 2px solid #1f2937; }
            .educon-sidebar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

            /* Los textos largos bajan de línea en lugar de provocar desplazamiento horizontal */
            .educon-sidebar nav a,
            .educon-sidebar nav button { max-width: 100%; min-width: 0; text-align: left; white-space: normal; overflow-wrap: anywhere; }
            .educon-sidebar nav button > span { min-width: 0; }
            .educon-sidebar nav svg { flex-shrink: 0; }

            /* Botones de colapsar / expandir (solo escritorio) */
            .educon-sidebar-toggle,
            .educon-sidebar-expand { display: none; }

            @media (min-width: 768px) {
                .educon-sidebar-desktop { transition: transform .2s ease-in-out; }
                .educon-main.with-sidebar { padding-left: 16rem; transition: padding-left .2s ease-in-out; }
                html.sidebar-collapsed .educon-sidebar-desktop { transform: translateX(-100%); }
                html.sidebar-collapsed .educon-main.with-sidebar { padding-left: 0; }

                .educon-sidebar-toggle {
                    display: inline-flex; align-items: center; justify-content: center;
                    margin-left: auto; height: 2rem; width: 2rem; border-radius: .375rem;
                    color: #9ca3af; background: transparent;
                }
                .educon-sidebar-toggle:hover { background: #374151; color: #fff; }
                .educon-sidebar-toggle:focus-visible,
                .educon-sidebar-expand:focus-visible { outline: 2px solid #818cf8; outline-offset: 2px; }

                html.sidebar-collapsed .educon-sidebar-expand {
                    display: inline-flex; align-items: center; justify-content: center;
                    position: fixed; left: 0; top: .875rem; z-index: 40;
                    height: 2.25rem; width: 1.75rem;
                    border-radius: 0 .375rem .375rem 0;
                    background: #1f2937; color: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .3);
                }
                html.sidebar-collapsed .educon-sidebar-expand:hover { background: #374151; width: 2rem; }
            }
        </style>

        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        {{-- [MODIFICADO] sidebarCollapsed: sidebar de escritorio oculto (se recuerda en el navegador) --}}
        <div x-data="{
                sidebarOpen: false,
                sidebarCollapsed: document.documentElement.classList.contains('sidebar-collapsed'),
                toggleSidebar() {
                    this.sidebarCollapsed = ! this.sidebarCollapsed;
                    document.documentElement.classList.toggle('sidebar-collapsed', this.sidebarCollapsed);
                    try { localStorage.setItem('educon.sidebarCollapsed', this.sidebarCollapsed ? '1' : '0'); } catch (e) {}
                }
             }" class="min-h-screen bg-gray-100">
            
            {{-- Sidebar: administrativo (Livewire) o del docente (parcial Blade) --}}
            {{-- [MODIFICADO] Docentes y coordinadores sin acceso administrativo tienen su propio sidebar --}}
            @php
                $sidebarType = ! Auth::check() ? null
                    : (Auth::user()->hasAdminAccess() ? 'admin' : (Auth::user()->usesTeacherSidebar() ? 'teacher' : null));
            @endphp
            @auth
            @if($sidebarType)
                
                {{-- Sidebar Desktop (Fijo a la izquierda) --}}
                <div class="educon-sidebar-desktop hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0"
                     :aria-hidden="sidebarCollapsed ? 'true' : 'false'" :inert="sidebarCollapsed">
                    @if($sidebarType === 'admin')
                        <livewire:layout.sidebar />
                    @else
                        @include('layouts.partials.teacher-sidebar')
                    @endif
                </div>

                {{-- [NUEVO] Pestaña para volver a mostrar el sidebar (solo visible si está colapsado) --}}
                <button type="button" class="educon-sidebar-expand" @click="toggleSidebar()"
                        title="Mostrar menú" aria-label="Mostrar menú lateral">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                </button>

                {{-- Sidebar Mobile (Overlay deslizante) --}}
                <div x-show="sidebarOpen" 
                     class="fixed inset-0 flex z-40 md:hidden" 
                     x-transition:enter="transition-opacity ease-linear duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-linear duration-300"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click.away="sidebarOpen = false">
                    
                    {{-- Fondo oscuro --}}
                    <div @click="sidebarOpen = false" class="fixed inset-0 bg-gray-600 bg-opacity-75" aria-hidden="true"></div>
                    
                    {{-- Panel del Sidebar --}}
                    <div class="relative flex-1 flex flex-col max-w-xs w-full"
                         x-transition:enter="transition ease-in-out duration-300 transform"
                         x-transition:enter-start="-translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transition ease-in-out duration-300 transform"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="-translate-x-full">
                        @if($sidebarType === 'admin')
                            <livewire:layout.sidebar />
                        @else
                            @include('layouts.partials.teacher-sidebar')
                        @endif
                    </div>
                </div>
            @endif
            @endauth

            {{-- Contenedor Principal (se ajusta automáticamente si hay sidebar) --}}
            <div @class([
                    'educon-main flex flex-col flex-1',
                    'with-sidebar' => (bool) $sidebarType
                 ])>
                
                {{-- Barra de Navegación Superior --}}
                @livewire('navigation-menu')

                {{-- Header de Página (opcional) --}}
                @if (isset($header))
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                {{-- Contenido Principal --}}
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('modals')
        @livewireScripts

        <script>
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('swal', (event) => {
                    // const data = event[0]; 
                    const data = Array.isArray(event) ? event[0] : event;
                    Swal.fire({
                        icon: data.icon || 'success',
                        title: data.title || '¡Hecho!',
                        text: data.text || '',
                        timer: data.timer || 3000,
                        timerProgressBar: data.timerProgressBar || true,
                        toast: data.toast || true, 
                        position: data.position || 'top-end',
                        showConfirmButton: data.showConfirmButton || false,
                    });
                });
                Livewire.on('swal:confirm', (event) => {
                    // const data = event[0];
                    const data = Array.isArray(event) ? event[0] : event;
                    Swal.fire({
                        title: data.title || '¿Estás seguro?',
                        text: data.text || '¡No podrás revertir esto!',
                        icon: data.icon || 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: data.confirmButtonText || 'Sí, ¡bórralo!',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Livewire.dispatch(data.onConfirmed, { id: data.id });
                        }
                    });
                });
            });
        </script>
      {{-- Stack para scripts adicionales (Chart.js, etc.) --}}
        @stack('scripts')
    </body>
</html>