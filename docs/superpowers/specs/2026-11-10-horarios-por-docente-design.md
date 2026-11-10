# Horarios por docente — diseño

**Fecha:** 2026-11-10 · **Ruta:** `/academic-process/schedules` (`academic-process.schedules`, permiso `gestionar-horarios`) · **Rama:** `horarios-por-docente`

## 1. Objetivo

Reorganizar la gestión de horarios para que parta de la **carga académica** ya registrada en `/academic-process/teacher-assignments`, en lugar de buscar unidades didácticas (UD) por nombre. El usuario elige programa → docente, ve el horario semanal completo del docente (espacios libres y ocupados) y configura los bloques de las UD que el docente tiene asignadas en ese programa.

**Éxito:** no es necesario buscar cursos por nombre; antes de guardar se ve la ocupación real del docente; no se pueden guardar bloques que crucen horarios, salgan del turno o superen las horas semanales de la UD.

## 2. Decisiones acordadas

| Tema | Decisión |
|---|---|
| Docente con carga en varios programas | **Grilla completa**: se ven todos sus bloques; los de otros programas en gris y solo lectura. Solo se programan sus UD del programa seleccionado. |
| Vista consolidada y exportación | **Se mantienen**, con las correcciones de la auditoría. El flujo nuevo reemplaza solo la vista "por unidad". |
| Horas semanales de la UD | **Bloquear** si se superan (por sección). Si la UD no tiene `weekly_hours`, solo avisar. |
| Horario del turno | **Bloquear** bloques fuera del turno de la sección. Si el turno no tiene horas, solo avisar. |
| Período | **Solo el período `active`** (como hoy). |
| Implementación | Reescribir el modo "por unidad" dentro de `ScheduleManager` y extraer las reglas a `App\Services\ScheduleValidationService`. Sin rutas, permisos ni migraciones nuevas. |

## 3. Contexto del modelo de datos

- `teachers` **no tiene `career_id`**: la pertenencia de un docente a un programa se deriva de su carga: asignaciones `active` del período activo cuya UD pertenece al programa (`didacticUnit.module.studyPlan.career_id`).
- `TeacherAssignment` (docente, UD, sección, turno, período, estado) contiene todo lo necesario. `Schedule` cuelga de la asignación (`teacher_assignment_id`, `day_of_week`, `start_time`, `end_time`, `classroom_resource_id`).
- `Schedule`/`Shift` usan `App\Casts\TimeCast` (Carbon al leer, `H:i:s` al guardar; comparar con `TimeCast::normalize()`).
- Turnos actuales: Diurno 08:00–12:30, Tarde 13:30–18:00, Nocturno 18:30–22:30.

## 4. Flujo e interfaz — vista "Por docente"

Cabecera: período activo (solo lectura) y botones **Por docente** | **Consolidada** | **Exportar**. Sin período activo: mensaje actual y no se muestra el flujo.

1. **Programa de estudios** — select de programas `active`. Al cambiar: se limpian docente, asignación seleccionada, bloque en edición y formulario.
2. **Docente** — select con los docentes que tienen asignaciones `active` del período activo en UD del programa, ordenados por apellido; cada opción con nombre y número de UD en el programa. Si no hay ninguno: "Sin carga académica en este programa" con enlace a `academic-process.teacher-assignments`. Al cambiar: se limpian asignación, bloque en edición y formulario.
3. **Horario semanal del docente** — grilla lunes–sábado; filas desde el inicio del turno más temprano hasta el fin del más tardío entre las secciones del docente en el período (si ningún turno tiene horas: 07:00–23:00), ampliado si algún bloque existente queda fuera de ese rango.
   - Bloques del programa seleccionado: color por UD; muestran UD, sección, aula. Clic → carga el bloque en el formulario (editar / eliminar).
   - Bloques de otros programas: gris, solo lectura, con el nombre del programa.
   - Icono de advertencia en bloques fuera de turno.
4. **Unidades didácticas del docente en el programa** — tarjetas por asignación: UD, semestre, sección, turno y horas programadas / semanales con barra de progreso (advertencia si ya se superan). Al elegir una se habilita el formulario:
   - día (lunes–sábado), inicio, fin (`<input type="time">` con `min`/`max` del turno) y botón **Usar horario del turno**;
   - aula opcional (solo `available`);
   - indicador "Quedan X h" (o aviso si la UD no tiene horas semanales);
   - **Guardar** crea o actualiza; **Cancelar edición**; **Eliminar** con `swal:confirm`.
   - Tras guardar/eliminar se recargan grilla y tarjetas.

Se eliminan del modo por docente: buscador de UD por nombre (`searchUnit`, `unitResults`, `selectUnit`), selector de sección y los botones rápidos "Mañana (08-13)" / "Noche (17-21)" (no coinciden con los turnos reales).

## 5. Reglas de validación — `ScheduleValidationService`

API propuesta:

```php
// Devuelve el primer error (mensaje para el usuario) o null si el bloque es válido.
public function validate(TeacherAssignment $assignment, string $day, string $start, string $end,
                         ?int $classroomId, ?int $ignoreScheduleId = null): ?string;

public function scheduledMinutes(TeacherAssignment $assignment, ?int $ignoreScheduleId = null): int;
public function remainingMinutes(TeacherAssignment $assignment, ?int $ignoreScheduleId = null): ?int; // null = UD sin weekly_hours
public function isWithinShift(TeacherAssignment $assignment, string $start, string $end): ?bool;      // null = turno sin horas
```

**0. Resolución en servidor (componente, antes del servicio).** La asignación se vuelve a buscar con: período activo, `status = active`, `teacher_id` = docente seleccionado y UD del programa seleccionado. El bloque en edición debe pertenecer a esa asignación. Si no cumple → se limpia la selección y no se guarda. Ningún id del formulario se usa sin esta resolución.

**1. Datos.** `day_of_week` ∈ lunes–sábado; `start_time`/`end_time` `date_format:H:i`, fin > inicio; aula `nullable`, existente y `status = available`.

**2. Turno.** `inicio ≥ shift.start_time` y `fin ≤ shift.end_time`. Mensaje: *"El turno Diurno es de 08:00 a 12:30."* Turno sin horas → no bloquea, aviso.

**3. Horas semanales (por sección).** `minutos programados de la asignación (sin el bloque en edición) + minutos del bloque ≤ weekly_hours × 60`. Mensaje: *"La UD tiene 4 h semanales; ya hay 3 h programadas, quedan 1 h."* `weekly_hours` vacío o 0 → no bloquea, aviso. Cada sección (A, B…) tiene sus propias horas.

**4. Cruces** (período activo, asignaciones `active`, solapamiento estricto `inicio < fin_otro && fin > inicio_otro`; bloques consecutivos no chocan), en este orden:
1. misma sección;
2. mismo docente en cualquier programa;
3. mismo grupo de estudiantes: programa + semestre + turno + sección;
4. misma aula.

Los mensajes indican UD, sección y rango del bloque con el que se cruza (como hoy).

**Bloques existentes que incumplen reglas nuevas:** no se modifican ni eliminan automáticamente; se marcan en grilla/tarjetas. Al editarlos se aplican todas las reglas.

## 6. Correcciones de la auditoría incluidas

1. `permission:gestionar-horarios` en `schedules/export`, `schedules/export-consolidated` y `schedules/export-consolidated-pdf`.
2. `exportConsolidated` / `exportConsolidatedPdf`: `validate()` (`career`, `cycle`, `shift`, `period_id` requeridos y existentes) → error de validación en lugar de 500.
3. Filtro `status = active` en `consolidatedSchedule()`, `resetSemesterSchedules()`, `exportConsolidatedPdf()` y `App\Exports\SemesterScheduleExport` (hoy no lo filtra).
4. `progressStats()`: `scheduled_courses` cuenta UD distintas (`distinct didactic_unit_id`) de asignaciones activas → el porcentaje no supera 100 %.
5. Vista consolidada sin período activo: no usar `$activePeriod->id` fuera de guarda → sin error 500.
6. Eliminar bloque y "Reiniciar semestre" con `swal:confirm` (patrón del proyecto); el reinicio indica cuántos bloques se eliminarán.
7. `orderByRaw("FIELD(day_of_week, ...)")` en `ScheduleReportController` → orden en PHP (permite probar en SQLite).
8. `selectedUnitId` manipulable desaparece: la UD se obtiene de la asignación resuelta en servidor.

## 7. Componentes y archivos

| Archivo | Cambio |
|---|---|
| `app/Services/ScheduleValidationService.php` | **Nuevo**: reglas de turno, horas y cruces (sección 5). |
| `app/Livewire/Pages/AcademicProcess/Schedules/ScheduleManager.php` | Modo por docente (propiedades `selectedCareerId`, `selectedTeacherId`, `selectedAssignmentId`, formulario; computed de docentes del programa, asignaciones del docente y grilla); delega validación al servicio; correcciones 3–6 y 8. Se mantiene `authorize('gestionar-horarios')` en toda acción mutadora y listeners `#[On]`. |
| `resources/views/livewire/pages/academic-process/schedules/schedule-manager.blade.php` | Nueva vista "Por docente" (pasos 1–4); consolidada con guarda de período y `swal:confirm`. |
| `app/Http/Controllers/ScheduleReportController.php` | Validación de consolidados, filtro `active`, orden en PHP. |
| `routes/web.php` | Permiso en las 3 rutas de exportación. |
| `CLAUDE.md` | Actualizar la sección "Horarios" con el flujo y las reglas nuevas. |

No se editan los respaldos `ScheduleManager_.php` ni `routes/web_.php`.

## 8. Pruebas

- **`tests/Feature/AcademicProcess/ScheduleValidationServiceTest.php`** (nuevo): turno (antes/después/bordes/sin horas); horas (completar exacto, superar, edición sin doble conteo, sin `weekly_hours`, secciones independientes); cruces (sección, docente en otro programa, grupo, aula, consecutivos, otro período/inactivas ignoradas); aula no disponible.
- **`tests/Feature/AcademicProcess/ScheduleManagerTest.php`** (reescrito): docentes filtrados por programa y período activo; cambio de programa/docente limpia dependientes; grilla con bloques de otro programa en solo lectura; lista con UD del programa; ids manipulados (asignación de otro docente/programa/período/inactiva, bloque de otra sección) no guardan ni eliminan; 403 sin permiso; crear/editar y "Quedan X h"; consolidada (inactivas ignoradas, % ≤ 100 con dos secciones, sin 500 sin período activo, reinicio solo de asignaciones activas filtradas).
- **`tests/Feature/AcademicProcess/ScheduleReportControllerTest.php`** (nuevo): 403 para Estudiante y 200 con `gestionar-horarios` en las 3 exportaciones; consolidados sin parámetros o con ids inexistentes → error de validación, no 500.
- Restricciones SQLite: sin `CONCAT` ni `FIELD`; `ShiftFactory` vacía (crear turnos con `Shift::create`); `TeacherFactory` requiere `Institution` y rol `Docente`.
- La suite completa debe mantener solo los 5 fallos preexistentes documentados en `CLAUDE.md`.

## 9. Despliegue

- Sin migraciones ni permisos nuevos. En el servidor: `php artisan optimize:clear`.
- Comunicar a los usuarios de horarios: la vista "por unidad" pasa a "por docente"; ahora se bloquean bloques fuera de turno o que superan las horas de la UD; los bloques existentes que incumplen quedan marcados, no se eliminan.
- Commits por etapa en `horarios-por-docente`: (1) servicio + tests, (2) componente y vista + tests, (3) correcciones de exportación/consolidada + tests, (4) documentación.

## 10. Fuera de alcance

Selección de período `planned`; asignación de docentes a programas en `teachers`; rediseño de la vista consolidada o de los reportes; cambios en `teacher-assignments`.
