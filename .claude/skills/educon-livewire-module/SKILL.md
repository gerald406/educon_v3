---
name: educon-livewire-module
description: Usar al crear o ampliar una página/CRUD de EduCON (componente Livewire de página completa + vista Blade + ruta + permiso + entrada de sidebar). Sigue el patrón *Manager existente.
---

# Nueva página Livewire en EduCON

Referencia viva del patrón: `app/Livewire/Pages/AcademicProcess/TeacherAssignments/TeacherAssignmentManager.php` y su vista. Léela antes de escribir código nuevo.

## Checklist

1. **Componente** en `app/Livewire/Pages/<Módulo>/<Sub>/<Entidad>Manager.php`
   - `#[Layout('layouts.app')]`, `use WithPagination, AuthorizesRequests;`
   - Filtros públicos (`$search`, `$filterPeriodId`, …) y campos del formulario como propiedades públicas + `rules()`.
   - `mount()`: cargar catálogos estáticos (`Collection`) y el período activo (`AcademicPeriod::where('status','active')`).
   - Acciones: `create()`, `edit(Model $m)`, `save()`, `confirmDelete($id)`, `#[On('deleteX')] deleteX($id)`, `closeModal()`, `resetInput()`.
   - **`$this->authorize('<permiso>')` en TODA acción que muta, incluidos los listeners `#[On]`** (son invocables desde el cliente).
   - Escrituras multi-tabla dentro de `DB::transaction`.
   - `render()`: query con `with([...])` (evitar N+1), filtros, `->paginate(10)`. Resetear página en `updatedSearch()` / `updatedFilter*()` con `$this->resetPage()`.
   - Feedback: `$this->dispatch('swal', [...])` / `swal:confirm` (contrato en `layouts/app.blade.php`). No usar `session()->flash` en páginas nuevas.

2. **Vista** en `resources/views/livewire/pages/<módulo-kebab>/<sub-kebab>/<entidad>-manager.blade.php`
   - Un único elemento raíz. Tailwind, modal con `@if($isModalOpen)` o `x-dialog-modal`, `wire:model.live.debounce.300ms` en buscadores, `wire:key` en filas de bucles.
   - Copiar la estructura visual de una vista vecina del mismo módulo.

3. **Ruta** en `routes/web.php`, dentro del grupo del módulo (prefijo + `name()` existentes), con `->middleware('permission:<slug>')` si el grupo no lo cubre. Rutas para estudiantes: grupo `student.` (ya incluye `check.wellness`).

4. **Permiso** nuevo → seguir la skill `educon-permission`.

5. **Sidebar** en `resources/views/livewire/layout/sidebar.blade.php`: enlace dentro del bloque `@can/@canany` del módulo, con la clase activa `request()->routeIs('<ruta>')`.

6. **Verificar**: `php artisan route:list --name=<prefijo>`, cargar la página como usuario con y sin permiso, `vendor/bin/pint --dirty`.

7. **Aplicar las skills transversales**: `educon-security-review` (permiso, pertenencia y estado en cada acción), `educon-ux` (buscadores que limpian el id al escribir, selects encadenados, confirmaciones, `wire:loading`) y `educon-testing` (test del componente con permiso/sin permiso/id ajeno).

## No hacer
- No editar los archivos `*_`.php / `*.blade_.php` (respaldos).
- No poner lógica de negocio compartida en el componente: si otro módulo la necesita, va a `app/Services`.
- No usar `CONCAT`/funciones MySQL en código que deba correr en tests sin protegerlo.
