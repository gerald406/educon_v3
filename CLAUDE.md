# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Proyecto

**EduCON**: sistema de gestión académica para un Instituto de Educación Superior Tecnológico Público (IESTP) del Perú, alineado a la normativa MINEDU. Cubre admisión, estructura académica (programas de estudio → planes → módulos formativos → unidades didácticas), carga docente, horarios, sílabos y sesiones de aprendizaje, matrícula, notas y asistencia, certificación/egreso, biblioteca, tutoría y bienestar estudiantil (encuestas). El módulo de **tesorería fue retirado** (ver Dominio).

El sistema **está en producción**: el código se descargó del servidor (commit inicial) y la BD de producción es MySQL con datos reales. Todo cambio de esquema debe ser compatible con datos existentes.

Idioma: UI, comentarios, mensajes, nombres de permisos y roles en **español**; tablas, columnas, modelos y clases en **inglés** (plural snake_case en tablas).

## Stack

PHP 8.2 (XAMPP en Windows) · Laravel 12 · Livewire 3 · Jetstream 5 (stack Livewire, Fortify, Sanctum) · spatie/laravel-permission 6 · maatwebsite/excel · barryvdh/laravel-dompdf · picqer/php-barcode-generator · Tailwind 3 + Alpine (vía Livewire) · Vite 7 · SweetAlert2 (CDN en `layouts/app.blade.php`).

## Comandos

```bash
composer dev                         # serve + queue:listen + pail + vite (concurrently)
npm run dev | npm run build          # assets
php artisan migrate                  # MySQL local: BD laravel_educon (ver .env)
php artisan db:seed                  # institución, años, roles/permisos y usuario Administrador
php artisan db:seed --class=RolesAndPermissionsSeeder   # re-sincronizar permisos (idempotente: firstOrCreate)
php artisan test                     # toda la suite (sqlite :memory:, ver phpunit.xml)
php artisan test --filter=CareerTest                     # un test / clase
php artisan test tests/Feature/Academic/CareerTest.php   # un archivo
vendor/bin/pint --dirty              # formato de los archivos modificados
php artisan optimize:clear           # tras cambiar rutas/config/permisos
```

**Estado de los tests:** la suite corre en SQLite (el `fullText` de `create_library_resources_table` solo se crea en MySQL). Fallan 5 tests **preexistentes y ajenos** a sílabos/horarios: 3 de `CareerTest` (`MethodNotFoundException`), `AdmissionDashboardTest > metrics are calculated correctly` y `ProfileInformationTest`; cualquier otro fallo es una regresión. Para tests nuevos:
- `TeacherFactory` necesita una `Institution` creada y el rol `Docente` (`Role::firstOrCreate(['name' => 'Docente'])`); `ShiftFactory` está vacía: crear turnos con `Shift::create([...])`. `AcademicPeriodFactory` usa un código fijo por año (`<año>-I`): para un segundo período pasar `code` distinto.
- `CONCAT(...)` (buscadores de personas) y `orderByRaw("FIELD(...)")` no existen en SQLite: evitarlos en código nuevo o no ejercitarlos en tests.
- Ejemplos: `tests/Feature/Syllabus/SyllabusRecoveryWeeksTest.php`, `tests/Feature/AcademicProcess/ScheduleManagerTest.php`.

## Skills del proyecto (`.claude/skills/`)

Usarlas antes de trabajar en su ámbito: `educon-livewire-module` (páginas/CRUD), `educon-migration` (esquema), `educon-permission` (permisos/roles), `educon-report-export` (PDF/Excel), `educon-testing` (tests y verificación), `educon-security-review` (autorización, pertenencia, estado; revisar diffs) y `educon-ux` (formularios, buscadores, feedback, grillas).

## Arquitectura

### Páginas = componentes Livewire de página completa
Casi toda la app son componentes en `app/Livewire/Pages/<Módulo>/...` registrados directamente como rutas (`Route::get('x', XManager::class)`) con `#[Layout('layouts.app')]`. La vista vive en el espejo kebab-case: `resources/views/livewire/pages/<módulo>/<...>/<x-manager>.blade.php`. Los controladores (`app/Http/Controllers`) sólo existen para **descargas** (PDF con DomPDF, Excel) y para el control de ingreso público al examen de admisión.

Patrón típico de un `*Manager` (ver `TeacherAssignments/TeacherAssignmentManager.php`):
- `WithPagination` + filtros públicos (`$search`, `$filterPeriodId`…); el query se arma en `render()` y se pagina ahí.
- Formulario como propiedades públicas sueltas + `rules()`; modal controlado por `$isModalOpen`; `create()/edit()/save()/closeModal()/resetInput()`.
- Buscadores predictivos con `updatedSearchX()` llenando `$xResults` (mín. 2 caracteres, `take(5..10)`). **`updatedSearchX()` debe limpiar el id seleccionado** (`$x_id = ''`) y `selectX($id)` recibir **solo el id** y leer el resto de la BD: si se conserva el id anterior al escribir otro texto, se guarda/sobrescribe el registro equivocado; y pasar nombres como `'{{ $nombre }}'` dentro de `wire:click` se rompe con apóstrofos. Al cambiar un filtro padre (carrera → curso → sección) resetear todo lo que depende de él.
- Feedback al usuario **siempre** vía eventos del navegador escuchados en `layouts/app.blade.php`:
  - `$this->dispatch('swal', ['icon'=>..., 'title'=>..., 'text'=>...])` → toast.
  - `$this->dispatch('swal:confirm', [..., 'id'=>$id, 'onConfirmed'=>'deleteX'])` → confirmación que hace `Livewire.dispatch('deleteX', {id})`, capturado con `#[On('deleteX')]` en el componente.
- Período activo por defecto: `AcademicPeriod` con `status = 'active'`.
- Lógica de negocio reutilizable en `app/Services` (`EnrollmentService`, `TeacherWorkloadService` con límites 18–26 h semanales, `PersonDataService`). `app/Actions/Academic` sólo la usa el test de carreras; no es el patrón general.

### Autorización (spatie/permission) — tres capas que deben mantenerse sincronizadas
1. **Ruta**: grupos en `routes/web.php` con `permission:<slug>` o `role:A|B` (alias registrados en `bootstrap/app.php`).
2. **Componente**: `$this->authorize('<slug>')` (trait `AuthorizesRequests`) en cada acción mutadora; los permisos se resuelven como Gates.
3. **Menú**: `resources/views/livewire/layout/sidebar.blade.php` usa `@can/@canany`.

Además hay **dos listas duplicadas de permisos "administrativos"** que deciden si se muestra el sidebar: `User::hasAdminAccess()` y `App\Livewire\Layout\Sidebar::render()`. Un permiso nuevo para personal administrativo debe añadirse en ambas, y en `RolesAndPermissionsSeeder` (fuente de verdad de permisos/roles).

Roles: `Administrador`, `Coordinador`, `Docente`, `Estudiante`, `Secretario Academico`, `Tesoreria`, `Admisión`, `Externo`. Permisos con slug en español con guiones (`gestionar-carga-academica`, `registrar-pagos`…). `Docente`/`Coordinador` nunca ven el sidebar (usan la navegación superior; `User::isTeacher()`), aunque tengan permisos.

### Middleware `check.wellness`
`EnsureWellnessProfileCompleted` redirige a los `Estudiante` a `student.onboarding` hasta que completen datos demográficos (`phone`, `address`, `ubigeo_birth_id`, `origin_school_id`) y respondan la encuesta obligatoria activa (`SurveyForm::active()->mandatory()`). Las rutas nuevas para estudiantes deben ir bajo este middleware; las rutas que el wizard necesite deben agregarse a `$allowedRoutes` o se produce un bucle de redirección.

### Dominio (relaciones clave)
- `Career` → `StudyPlan` → `Module` → `DidacticUnit` (curso, con `weekly_hours`, créditos, semestre). Filtrar por carrera implica `whereHas('didacticUnit.module.studyPlan', fn => career_id)`.
- `TeacherAssignment` = sección de un curso en un período (`academic_period_id`, `teacher_id`, `didactic_unit_id`, `shift_id`, `section`, `max_capacity`, `status`). Es el eje de `Schedule`, `Syllabus` (→ `SyllabusUnit` → `LearningSession`), `Registration`, notas y asistencias. Unicidad lógica: curso + sección + período.
- Persona: `User` (Jetstream, `name`, `lastname`, `document_number` = DNI) 1–1 con `Teacher` o `Student`. Búsquedas por nombre usan `CONCAT(name, ' ', lastname)` (SQL específico de MySQL).
- `Applicant` (admisión) → se convierte en `Student`. `Location` = ubigeo.
- Tesorería **retirada** (commit `be7a560`): se eliminaron componentes, vistas, PDFs, controladores, rutas, menú y permisos; la matrícula ya no exige comprobante ni bloquea por deuda. Los modelos (`CashSession`, `VoucherSeries`, `Voucher`/`VoucherItem`, `CreditNote`, `StudentPayment`, `PaymentConcept`), migraciones y datos **se conservan**: no reintroducir dependencias de pago sin pedirlo.
- Bienestar: modelos en `App\Models\Wellness\Survey*` (formularios → secciones → preguntas → opciones; asignaciones; respuestas → answers).
- Estados como strings (`active`, `inactive`, `suspended`, `completed`…), no enums de PHP.

### Sílabos y sesiones de aprendizaje
- Cadena: `TeacherAssignment` 1–1 `Syllabus` → `SyllabusIndicator` → `SyllabusUnit` (una semana) 1–1 `LearningSession`. Componentes: `Teacher/MySyllabi`, `SyllabusEditor`, `LearningSessionList`, `LearningSessionEditor`, `AcademicProcess/SyllabusApproval`; PDFs `Teacher/SyllabusPdfController`, `LearningSessionPdfController`.
- Estados de `syllabi.status`: `draft → submitted → approved | observed` (+ `rejected`). `pending_approval` es **obsoleto** (no usarlo). Solo `draft`/`observed` son editables: toda acción mutadora de `SyllabusEditor` llama `ensureEditable()` (lee el estado en BD); ocultar botones no basta.
- **Semanas:** estructura nueva = 16 regulares (por indicador) + **17 y 18 de "Reforzamiento y recuperación de capacidades no logradas"** (`syllabus_units.type = 'recovery'`, `syllabus_id` directo, `syllabus_indicator_id` NULL). Textos fijos e instrumentos en constantes de `SyllabusUnit` (`RECOVERY_*`, `INSTRUMENTS`); solo el instrumento es editable. `SyllabusRecoveryService::shouldApply/ensureFor` (idempotente) se aplica a sílabos nuevos, editables (`draft`/`observed`, de cualquier período) o que ya tienen recuperación; solo los **enviados/aprobados** con la estructura anterior conservan **18 semanas regulares** (`Syllabus::maxRegularWeeks()`), para no modificar un documento en revisión o aprobado. En `SyllabusEditor`, `total_weeks_programmed` y `maxRegularWeeks` son `#[Locked]` y `addSession()` cuenta las semanas en BD; `submitSyllabus()` valida completitud con lo guardado (`Syllabus::isReadyForSubmission()`).
- Para "todas las semanas de un sílabo" usar `SyllabusUnit::ofSyllabus($id)` (regulares + recuperación); `whereHas('indicator', ...)` / `indicators.units` devuelven **solo regulares**. Pertenencia de una semana a un sílabo: `LearningSession::unitBelongsToSyllabus()` (verificarla en rutas `{syllabus}/{unit}`).
- Datos generales del sílabo (Tab I) son **solo lectura**: no modificar carrera, plan, módulo, UD, turno ni usuario desde el sílabo.
- En producción `syllabus_indicators`, `syllabus_units`, `learning_sessions` y varias columnas de `syllabi` se crearon **fuera de las migraciones**; `2026_11_09_100000_create_syllabus_structure_tables_if_missing` las crea solo si faltan (no-op en producción, `down()` vacío a propósito).

### Horarios (`academic-process/schedules`)
- `Schedule` y `Shift` castean `start_time`/`end_time` con `App\Casts\TimeCast`: se leen como **Carbon** (`->format('H:i')`, `diffInMinutes`) y se guardan como `H:i:s`. No volver a `'time'`/`'string'` (Laravel no tiene cast `time`; con texto ~40 vistas/reportes fallan). En consultas, comparar contra `TimeCast::normalize($hora)`.
- Flujo **por docente** (spec `docs/superpowers/specs/2026-11-10-horarios-por-docente-design.md`): programa → docente (solo con carga `active` del período activo en UD del programa; `teachers` no tiene `career_id`) → grilla con **todos** sus bloques (los de otros programas en solo lectura) → sus UD del programa → bloques. Sin buscador de UD por nombre.
- Reglas en `App\Services\ScheduleValidationService::validate()`: aula `available`, bloque dentro del **turno** de la sección, sin superar `weekly_hours` de la UD (por sección) y sin cruces (sección, docente en cualquier programa, grupo programa + semestre + turno + sección, aula), solo período de la sección y asignaciones `active`. Turno sin horas o UD sin `weekly_hours` solo avisan (`warnings()`). Los bloques existentes que incumplen se marcan, no se borran.
- `ScheduleManager::resolveAssignment()` revalida en servidor toda sección/bloque (período activo + `active` + docente + programa seleccionados); no confiar en ids del formulario. `teacher_assignments.status` ∈ `active|suspended|completed`.
- Exportaciones (`schedules/export*`) exigen `permission:gestionar-horarios`; los consolidados validan parámetros y, como la vista consolidada y "Reiniciar semestre", solo consideran secciones `active`.

### Reportes
- Excel: clases en `app/Exports` con `FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle`; se descargan desde un método Livewire (`return Excel::download(...)`) o desde un controlador.
- PDF: DomPDF en controladores con vistas en `resources/views/reports/**`.

## Convenciones y trampas

- Archivos con sufijo `_` (`ScheduleManager_.php`, `SyllabusApproval_.php`, `AdmissionRankingExport_.php`, `*.blade_.php`, `routes/web_.php`) son **copias de respaldo** que declaran la misma clase que el original: no editarlas ni referenciarlas. `app/Exports/ExamReportController.php` declara un controlador en el namespace equivocado (duplicado del de `Http/Controllers`).
- Los comentarios del código marcan cambios con `[NUEVO]`, `[MODIFICADO]`, `[CORREGIDO]`, `[REQUERIMIENTO]`; respetar el estilo de comentarios del archivo que se edita.
- Las rutas comentadas de bienestar (`assignments`, `responses`, `reports`) tienen componentes ya creados pero aún no publicados.
- Raíz del repo contiene volcados y respaldos versionados (`laravel_educon.sql`, `laraveleducon.sql`, `educon.zip`, `codigo_completo.txt`): no leerlos completos ni modificarlos; pueden contener datos personales.
- `create_library_resources_table` (ya ejecutada) se editó solo para condicionar el `fullText` al driver: es la única excepción a "no editar migraciones ejecutadas", porque no cambia nada en producción.
- Producción usa MySQL: evitar SQL que dependa de un motor si no se protege con el driver, y en migraciones sobre tablas existentes preferir columnas `nullable()`/con `default` e índices en migraciones separadas (`add_indexes_to_*`).
