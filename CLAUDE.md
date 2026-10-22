# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Proyecto

**EduCON**: sistema de gestión académica para un Instituto de Educación Superior Tecnológico Público (IESTP) del Perú, alineado a la normativa MINEDU. Cubre admisión, estructura académica (programas de estudio → planes → módulos formativos → unidades didácticas), carga docente, horarios, sílabos y sesiones de aprendizaje, matrícula, notas y asistencia, tesorería (comprobantes, caja, TUPA), certificación/egreso, biblioteca, tutoría y bienestar estudiantil (encuestas).

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

**Estado de los tests:** la suite falla casi entera (42/44) porque `create_library_resources_table` usa `$table->fullText(...)`, que SQLite no soporta, y todas las pruebas con `RefreshDatabase` revientan en la migración. Cualquier cambio verificado con tests requiere primero resolver eso (p. ej. condicionar el índice a `DB::getDriverName() === 'mysql'`).

## Arquitectura

### Páginas = componentes Livewire de página completa
Casi toda la app son componentes en `app/Livewire/Pages/<Módulo>/...` registrados directamente como rutas (`Route::get('x', XManager::class)`) con `#[Layout('layouts.app')]`. La vista vive en el espejo kebab-case: `resources/views/livewire/pages/<módulo>/<...>/<x-manager>.blade.php`. Los controladores (`app/Http/Controllers`) sólo existen para **descargas** (PDF con DomPDF, Excel) y para el control de ingreso público al examen de admisión.

Patrón típico de un `*Manager` (ver `TeacherAssignments/TeacherAssignmentManager.php`):
- `WithPagination` + filtros públicos (`$search`, `$filterPeriodId`…); el query se arma en `render()` y se pagina ahí.
- Formulario como propiedades públicas sueltas + `rules()`; modal controlado por `$isModalOpen`; `create()/edit()/save()/closeModal()/resetInput()`.
- Buscadores predictivos con `updatedSearchX()` llenando `$xResults` (mín. 2 caracteres, `take(5..10)`).
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
- Tesorería: `CashSession`, `VoucherSeries` (correlativos), `Voucher`/`VoucherItem`, `CreditNote`, `StudentPayment`, `PaymentConcept` (TUPA).
- Bienestar: modelos en `App\Models\Wellness\Survey*` (formularios → secciones → preguntas → opciones; asignaciones; respuestas → answers).
- Estados como strings (`active`, `inactive`, `suspended`, `completed`…), no enums de PHP.

### Reportes
- Excel: clases en `app/Exports` con `FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle`; se descargan desde un método Livewire (`return Excel::download(...)`) o desde un controlador.
- PDF: DomPDF en controladores con vistas en `resources/views/reports/**`.

## Convenciones y trampas

- Archivos con sufijo `_` (`ScheduleManager_.php`, `SyllabusApproval_.php`, `AdmissionRankingExport_.php`, `*.blade_.php`, `routes/web_.php`) son **copias de respaldo** que declaran la misma clase que el original: no editarlas ni referenciarlas. `app/Exports/ExamReportController.php` declara un controlador en el namespace equivocado (duplicado del de `Http/Controllers`).
- Los comentarios del código marcan cambios con `[NUEVO]`, `[MODIFICADO]`, `[CORREGIDO]`, `[REQUERIMIENTO]`; respetar el estilo de comentarios del archivo que se edita.
- Las rutas comentadas de bienestar (`assignments`, `responses`, `reports`) tienen componentes ya creados pero aún no publicados.
- Raíz del repo contiene volcados y respaldos versionados (`laravel_educon.sql`, `laraveleducon.sql`, `educon.zip`, `codigo_completo.txt`): no leerlos completos ni modificarlos; pueden contener datos personales.
- Producción usa MySQL: evitar SQL que dependa de un motor si no se protege con el driver, y en migraciones sobre tablas existentes preferir columnas `nullable()`/con `default` e índices en migraciones separadas (`add_indexes_to_*`).
