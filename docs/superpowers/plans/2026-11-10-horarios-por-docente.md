# Horarios por docente — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar la vista "por unidad" de `/academic-process/schedules` por el flujo programa → docente → grilla completa del docente → UD asignadas, con reglas de turno, horas semanales y cruces en un servicio, y corregir los hallazgos de la auditoría en consolidada y exportaciones.

**Architecture:** Las reglas se extraen a `App\Services\ScheduleValidationService` (sin estado, probado en aislamiento). `ScheduleManager` conserva ruta, permiso y vista consolidada; su modo "por unidad" se reescribe como modo "por docente" que resuelve todo id en servidor y delega la validación al servicio. `ScheduleReportController` y `SemesterScheduleExport` reciben permiso, validación y filtro `active`.

**Tech Stack:** PHP 8.2 · Laravel 12 · Livewire 3 · spatie/laravel-permission 6 · maatwebsite/excel · DomPDF · PHPUnit (SQLite :memory:).

**Spec:** `docs/superpowers/specs/2026-11-10-horarios-por-docente-design.md`

## Global Constraints

- Solo período `AcademicPeriod::where('status', 'active')`; solo asignaciones `status = 'active'`.
- Días: `['monday','tuesday','wednesday','thursday','friday','saturday']` (constante `ScheduleManager::DAYS`, se mueve a `ScheduleValidationService::DAYS`).
- Horas: comparar siempre con `TimeCast::normalize()`; `start_time`/`end_time` de `Schedule` y `Shift` se leen como Carbon.
- Solapamiento estricto: `start < other.end && end > other.start` (bloques consecutivos no chocan).
- Sin migraciones, rutas ni permisos nuevos. Permiso único: `gestionar-horarios`.
- UI, mensajes y comentarios en español; marcar cambios con `[NUEVO]`/`[MODIFICADO]`/`[CORREGIDO]`.
- Feedback solo por `dispatch('swal', [...])` / `dispatch('swal:confirm', [...])` (ver `layouts/app.blade.php`).
- Código nuevo sin `CONCAT(...)` ni `FIELD(...)`; tests en SQLite: `Shift::create([...])`, `TeacherFactory` necesita `Institution` y rol `Docente`.
- No editar `ScheduleManager_.php` ni `routes/web_.php`.
- La suite completa debe quedar con solo los 5 fallos preexistentes (3 de `CareerTest`, `AdmissionDashboardTest > metrics are calculated correctly`, `ProfileInformationTest`).

## Review Focus

1. **UD con horas fraccionarias** (`weekly_hours = 4.5`): el límite es 270 min; un bloque que lleva el total a 270 se acepta. → test en Task 1.
2. **Asignación sin turno** (`shift_id` null o turno sin horas): no debe dar 500; no se bloquea por turno, se avisa, y la grilla usa 07:00–23:00. → tests en Task 1 y Task 2.
3. **Cambiar de docente con un bloque en edición**: el formulario y `editingScheduleId` se limpian; guardar después no modifica el bloque del docente anterior. → test en Task 2.
4. **Editar un bloque existente fuera de turno cambiando solo el aula**: se rechaza con el mensaje del turno (las reglas nuevas aplican al editar), y el bloque queda sin cambios. → test en Task 2.
5. **Docente con UD en el programa pero solo en un período `planned`**: no aparece en la lista de docentes. → test en Task 2.

---

### Task 1: `ScheduleValidationService`

**Files:**
- Create: `app/Services/ScheduleValidationService.php`
- Test: `tests/Feature/AcademicProcess/ScheduleValidationServiceTest.php`

**Interfaces:**
- Produces:
  - `public const DAYS = ['monday','tuesday','wednesday','thursday','friday','saturday'];`
  - `validate(TeacherAssignment $assignment, string $day, string $start, string $end, ?int $classroomId, ?int $ignoreScheduleId = null): ?string` — `$start`/`$end` en `H:i`; devuelve el primer mensaje de error o `null`. Orden: aula disponible → turno → horas → cruces (sección, docente, grupo, aula).
  - `scheduledMinutes(TeacherAssignment $assignment, ?int $ignoreScheduleId = null): int`
  - `remainingMinutes(TeacherAssignment $assignment, ?int $ignoreScheduleId = null): ?int` — `null` si `weekly_hours` vacío/0.
  - `isWithinShift(TeacherAssignment $assignment, string $start, string $end): ?bool` — `null` si la asignación no tiene turno o el turno no tiene horas.
  - `warnings(TeacherAssignment $assignment): array` — lista de avisos no bloqueantes (`'La UD no tiene horas semanales registradas.'`, `'El turno de la sección no tiene horario registrado.'`).

Los cruces se buscan con `Schedule::whereHas('teacherAssignment', period activo de la asignación + status active)`, excluyendo `$ignoreScheduleId`. El período es `$assignment->academic_period_id` (el componente ya garantiza que es el activo).

- [ ] **Step 1: Write the failing tests** (setup copiado del `setUp()` actual de `ScheduleManagerTest`: institución, período activo, programa/plan/módulo, turno `Shift::create(['name'=>'Diurno','start_time'=>'08:00','end_time'=>'12:30'])`; helpers `unit()` y `section()` iguales).

```php
test_block_inside_shift_borders_is_valid()                 // 08:00–10:00 y 10:30–12:30 → null
test_block_starting_before_shift_is_rejected()             // 07:30–09:00 → 'El turno Diurno es de 08:00 a 12:30.'
test_block_ending_after_shift_is_rejected()                // 11:00–13:00 → mismo mensaje
test_shift_without_hours_does_not_block()                  // turno con start/end null → null; warnings() contiene el aviso de turno
test_block_completing_weekly_hours_is_valid()              // weekly_hours 4, existente 08:00–11:00, nuevo 11:00–12:00 → null
test_block_exceeding_weekly_hours_is_rejected()            // existente 3 h, nuevo 2 h → 'La UD tiene 4 h semanales; ya hay 3 h programadas, quedan 1 h.'
test_fractional_weekly_hours_allow_exact_total()           // weekly_hours 4.5, existente 08:00–11:00, nuevo 11:00–12:30 → null; remainingMinutes = 90 antes
test_editing_block_is_not_counted_twice()                  // existente 08:00–12:00 (4 h), validate(..., '08:00','12:00', null, $existing->id) → null
test_unit_without_weekly_hours_does_not_block()            // weekly_hours null → null; remainingMinutes null; warnings contiene aviso de horas
test_sections_have_independent_hours()                     // A con 4 h programadas; B de la misma UD acepta 08:00–12:00
test_overlap_in_same_section_is_rejected()                 // mensaje empieza con 'Esta sección ya tiene un bloque'
test_teacher_overlap_in_another_program_is_rejected()      // mismo teacher_id, UD de otro Career → mensaje empieza con 'El docente ya tiene clase'
test_student_group_overlap_is_rejected()                   // otra UD mismo programa+semestre+turno+sección → 'Los estudiantes del semestre 1, sección A'
test_classroom_overlap_is_rejected()                       // otra sección, misma aula → empieza con 'El aula'
test_consecutive_blocks_do_not_conflict()                  // 08:00–10:00 existente, nuevo 10:00–12:00 mismo docente → null
test_other_period_and_inactive_assignments_are_ignored()   // choque con asignación de período planned y con asignación inactive → null
test_unavailable_classroom_is_rejected()                   // ClassroomResource status 'maintenance' → 'El aula seleccionada no está disponible.'
```

- [ ] **Step 2: Run** `php artisan test tests/Feature/AcademicProcess/ScheduleValidationServiceTest.php` — Expected: FAIL (`Class "App\Services\ScheduleValidationService" not found`).

- [ ] **Step 3: Implement** el servicio con las firmas de Interfaces. Mensajes exactos: los de los tests; turno: `"El turno {$shift->name} es de {H:i} a {H:i}."`; horas: `"La UD tiene {h} h semanales; ya hay {h} h programadas, quedan {h} h."` con horas formateadas sin ceros sobrantes (`4`, `4.5`, `1.5`). Mover aquí la lógica de `ScheduleManager::findConflict()` (líneas 378–436) sin cambiar sus mensajes; el aula se recibe por parámetro (no leer propiedades del componente).

- [ ] **Step 4: Run** el mismo comando — Expected: PASS (17 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/ScheduleValidationService.php tests/Feature/AcademicProcess/ScheduleValidationServiceTest.php
git commit -m "horarios: servicio de validacion (turno, horas semanales y cruces)"
```

---

### Task 2: Modo "por docente" en `ScheduleManager`

**Files:**
- Modify: `app/Livewire/Pages/AcademicProcess/Schedules/ScheduleManager.php` (modo unidad: líneas 32–460)
- Modify: `resources/views/livewire/pages/academic-process/schedules/schedule-manager.blade.php` (modo unidad: líneas 1–317)
- Test: `tests/Feature/AcademicProcess/ScheduleManagerTest.php` (reescribir los tests del modo unidad; conservar `test_schedule_times_are_carbon_and_stored_normalized` y `test_editing_an_assignment_and_typing_another_teacher_does_not_overwrite_it`)

**Interfaces:**
- Consumes: `ScheduleValidationService` (Task 1): `validate`, `scheduledMinutes`, `remainingMinutes`, `warnings`, `DAYS`.
- Produces (propiedades/métodos públicos que usan vista y tests):
  - `$viewMode` valores `'teacher'` (por defecto, reemplaza `'unit'`) | `'consolidated'`.
  - `$selectedCareerId`, `$selectedTeacherId`, `$selectedAssignmentId`, `$editingScheduleId`, `$day_of_week`, `$start_time`, `$end_time`, `$classroom_resource_id`.
  - `#[Computed] programTeachers(): Collection` — `Teacher` con `assignments_count` en el programa (período activo, `active`), orden por `user.lastname` en PHP.
  - `#[Computed] teacherAssignments(): Collection` — asignaciones del docente en el programa, con `didacticUnit`, `shift`, y atributos calculados `scheduled_minutes`, `remaining_minutes`.
  - `#[Computed] teacherGrid(): array` — `['days' => DAYS, 'from' => 'H:i', 'to' => 'H:i', 'blocks' => [day => [ ['id','assignment_id','unit','section','classroom','start','end','editable'(bool),'program'(string),'out_of_shift'(bool)] ]]]`.
  - `selectAssignment(int $id): void`, `editSchedule(int $id): void`, `cancelEdit(): void`, `useShiftHours(): void`, `saveSchedule(): void` (reemplaza `addSchedule`), `confirmDeleteSchedule(int $id): void`, `#[On('deleteSchedule')] deleteSchedule(int $id): void`.
  - `private resolveAssignment($id): ?TeacherAssignment` — período activo + `active` + `teacher_id = selectedTeacherId` + UD del `selectedCareerId`.

- [ ] **Step 1: Write the failing tests** (reutilizar `setUp()`, `unit()`, `section()`; nuevo helper `managerForTeacher(Teacher $t)` que hace `set('selectedCareerId')->set('selectedTeacherId')`).

```php
test_teachers_are_listed_only_with_active_load_in_program()      // docente con UD del programa: aparece; docente solo en otro programa: no; docente con asignación inactive: no; solo en período planned: no (Review Focus 5)
test_changing_program_clears_teacher_assignment_and_form()       // assertSet('selectedTeacherId',''), ('selectedAssignmentId',''), ('editingScheduleId',null)
test_changing_teacher_clears_assignment_and_edit()               // con bloque en edición; luego saveSchedule no modifica ese bloque (Review Focus 3)
test_grid_shows_blocks_of_other_programs_read_only()             // teacherGrid: bloque propio editable=true; bloque de otro programa editable=false, program = nombre del otro Career
test_grid_range_covers_teacher_shifts()                          // turnos 08:00–12:30 y 18:30–22:30 → from '08:00', to '22:30'; sin horas → '07:00'–'23:00' (Review Focus 2); bloque existente 07:00–08:00 amplía from a '07:00'
test_assignment_list_only_has_program_units()                    // teacherAssignments sin la UD de otro programa
test_selecting_assignment_of_other_teacher_is_ignored()          // selectAssignment(id ajeno) → selectedAssignmentId '' ; saveSchedule no crea Schedule
test_cannot_edit_or_delete_block_of_another_section()            // editSchedule/deleteSchedule con id de otra sección → sin cambios
test_save_creates_block_and_shows_remaining_hours()              // saveSchedule → Schedule creado; assertSee('Quedan 2 h')
test_save_rejects_block_outside_shift()                          // assertDispatched('swal', icon error, text 'El turno Mañana es de 08:00 a 13:00.')
test_editing_out_of_shift_block_only_classroom_is_rejected()     // bloque 19:00–20:00 existente, editar y cambiar aula → swal error; bloque sin cambios (Review Focus 4)
test_use_shift_hours_fills_form()                                // useShiftHours → start_time '08:00', end_time '13:00'
test_delete_asks_confirmation_then_deletes()                     // confirmDeleteSchedule → assertDispatched('swal:confirm'); dispatch('deleteSchedule', id) borra
test_user_without_permission_gets_403()                          // saveSchedule y deleteSchedule → assertForbidden
```

- [ ] **Step 2: Run** `php artisan test tests/Feature/AcademicProcess/ScheduleManagerTest.php` — Expected: FAIL (propiedades/métodos inexistentes).

- [ ] **Step 3: Implement** el componente: quitar `searchUnit`, `unitResults`, `selectedUnitId`, `selectedUnitName`, `selectUnit`, `loadAssignments`, `findSelectableAssignment`, `setPresetTime`, `findConflict` y `$teachers` de `mount()`. `updatedSelectedCareerId` y `updatedSelectedTeacherId` limpian dependientes. `saveSchedule()`: `authorize('gestionar-horarios')` → `resolveAssignment` → `validate()` de formato (`day_of_week` in DAYS, `H:i`, `after:start_time`, aula `nullable|exists`) → `ScheduleValidationService::validate()` → crear/actualizar → swal de éxito. `deleteSchedule` con `authorize` y filtro por `resolveAssignment($this->selectedAssignmentId)`. Inyectar el servicio con `app(ScheduleValidationService::class)`.

- [ ] **Step 4: Implement** la vista según la sección 4 del spec: pasos 1–4, grilla con `teacherGrid` (bloques grises sin `wire:click` cuando `editable=false`; icono de aviso si `out_of_shift`), tarjetas con barra `scheduled/weekly`, formulario con `min`/`max` del turno, "Quedan X h" o aviso de `warnings()`. Enlace "Ir a carga académica" a `route('academic-process.teacher-assignments')` cuando `programTeachers` está vacío. Botón de modo `'teacher'` con texto "Por docente".

- [ ] **Step 5: Run** el test del archivo — Expected: PASS. Luego `php artisan test tests/Feature/AcademicProcess` — Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Pages/AcademicProcess/Schedules/ScheduleManager.php resources/views/livewire/pages/academic-process/schedules/schedule-manager.blade.php tests/Feature/AcademicProcess/ScheduleManagerTest.php
git commit -m "horarios: flujo por docente con grilla completa y UD de la carga academica"
```

---

### Task 3: Vista consolidada y exportaciones

**Files:**
- Modify: `routes/web.php:168-175`
- Modify: `app/Http/Controllers/ScheduleReportController.php`
- Modify: `app/Exports/SemesterScheduleExport.php:30-36`
- Modify: `ScheduleManager.php` (`consolidatedSchedule`, `progressStats`, `resetSemesterSchedules`) y la sección consolidada de la vista
- Test: `tests/Feature/AcademicProcess/ScheduleReportControllerTest.php` (nuevo) y `ScheduleManagerTest.php`

**Interfaces:**
- Consumes: `ScheduleValidationService::DAYS` para ordenar en PHP.
- Produces: `confirmResetSemesterSchedules(): void` (dispara `swal:confirm` con `onConfirmed => 'resetSemesterSchedules'` y el número de bloques en `text`); `#[On('resetSemesterSchedules')] resetSemesterSchedules(): void`.

- [ ] **Step 1: Write the failing tests**

```php
// ScheduleReportControllerTest
test_exports_are_forbidden_without_permission()        // usuario rol Estudiante: GET de las 3 rutas con parámetros válidos → 403
test_exports_work_with_permission()                    // con gestionar-horarios: schedules.export (type=teacher, format=pdf) → 200; export-consolidated-pdf → 200
test_consolidated_exports_validate_parameters()        // sin parámetros y con shift=999 → assertSessionHasErrors / 302 (no 500)
test_consolidated_pdf_ignores_inactive_assignments()   // bloque de asignación inactive no aparece en la vista: assertViewHas('schedules') sin ese id

// ScheduleManagerTest
test_consolidated_ignores_inactive_assignments()       // consolidatedSchedule sin el bloque de la asignación inactive
test_progress_never_exceeds_100_with_two_sections()    // 1 UD con secciones A y B programadas → scheduled_courses 1, percentage 100
test_consolidated_without_active_period_renders()      // sin período activo, viewMode consolidated + filtros → assertOk (sin excepción)
test_reset_semester_asks_confirmation_and_deletes_only_active() // confirm → swal:confirm con '2 bloques'; dispatch → borra activos, conserva el de asignación inactive
```

- [ ] **Step 2: Run** `php artisan test tests/Feature/AcademicProcess` — Expected: FAIL en los 8 nuevos.

- [ ] **Step 3: Implement**: `->middleware('permission:gestionar-horarios')` en las 3 rutas; `$request->validate(['career'=>'required|exists:careers,id','cycle'=>'required','shift'=>'required|exists:shifts,id','period_id'=>'required|exists:academic_periods,id'])` en ambos consolidados; `->where('status','active')` en las consultas de `teacherAssignment` del consolidado (controlador, export y componente); `progressStats` cuenta `distinct('didactic_unit_id')` de asignaciones activas; reemplazar `orderByRaw("FIELD(...)")` por orden en PHP con `array_search($day, DAYS)` + `start_time`; en la vista consolidada envolver los enlaces de exportación en `@if($activePeriod)`; reemplazar los `wire:confirm` por `confirmDeleteSchedule`/`confirmResetSemesterSchedules`.

- [ ] **Step 4: Run** `php artisan test tests/Feature/AcademicProcess` — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/ScheduleReportController.php app/Exports/SemesterScheduleExport.php app/Livewire/Pages/AcademicProcess/Schedules/ScheduleManager.php resources/views/livewire/pages/academic-process/schedules/schedule-manager.blade.php tests/Feature/AcademicProcess
git commit -m "horarios: permiso y validacion en exportaciones, consolidada solo con secciones activas"
```

---

### Task 4: Documentación y verificación final

**Files:**
- Modify: `CLAUDE.md` (sección "Horarios (`academic-process/schedules`)")

- [ ] **Step 1:** Reemplazar los puntos de `findConflict()` y `findSelectableAssignment` por: flujo programa → docente → grilla → UD; reglas en `ScheduleValidationService` (turno, horas semanales por sección, cruces); `resolveAssignment()` revalida en servidor; exportaciones con `permission:gestionar-horarios`.
- [ ] **Step 2: Run** `php artisan test` — Expected: solo los 5 fallos preexistentes.
- [ ] **Step 3: Verificación manual** en `http://127.0.0.1:8000/academic-process/schedules` con la BD local: elegir programa y el docente con carga en dos programas → la grilla muestra en gris los bloques del otro programa; el bloque existente fuera de turno aparece marcado.
- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "documentacion: horarios por docente en CLAUDE.md"
```
