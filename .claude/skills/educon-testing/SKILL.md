---
name: educon-testing
description: Usar al escribir o ejecutar tests en EduCON, al corregir un bug (test de regresión primero) o antes de dar por terminado un cambio. Cubre setup de factories, Livewire::test, límites de SQLite y la línea base de fallos conocidos.
---

# Tests en EduCON

Suite en SQLite `:memory:` (`phpunit.xml`); producción es MySQL. Referencias vivas: `tests/Feature/Syllabus/SyllabusRecoveryWeeksTest.php`, `tests/Feature/AcademicProcess/ScheduleManagerTest.php`.

## Flujo
1. **Bug:** escribir primero el test que lo reproduce y verlo fallar; luego corregir.
2. Ubicar el test en `tests/Feature/<Módulo>/<Componente>Test.php` con `RefreshDatabase`.
3. Ejecutar el archivo (`php artisan test tests/Feature/<Módulo>`) y al final **la suite completa**: `php artisan test`.
4. Comparar con la línea base: fallan 5 tests preexistentes y ajenos (3 de `CareerTest`, `AdmissionDashboardTest > metrics…`, `ProfileInformationTest`). Cualquier otro fallo es regresión. Reportar los números reales, no "todo pasa".

## Setup (trampas de las factories)
- `TeacherFactory` usa `Institution::first()` y asigna el rol `Docente`: crear `Institution::factory()->create(['status'=>'active'])` y `Role::firstOrCreate(['name'=>'Docente'])` en `setUp()`.
- `ShiftFactory` está vacía → `Shift::create(['name'=>..., 'start_time'=>'08:00', 'end_time'=>'13:00'])`.
- `AcademicPeriodFactory` genera código `<año>-I` (único por institución): un segundo período necesita `code` distinto. Su estado por defecto es `planned`; pasar `['status'=>'active']` cuando el componente busca el período activo.
- Permisos: `Permission::firstOrCreate([...])` + `$user->givePermissionTo(...)`.
- Para filtrar por carrera, crear `StudyPlan`/`Module` explícitos y reutilizarlos (las factories crean una carrera nueva por cada unidad).

## Qué cubrir en un componente Livewire
- **Camino feliz** con `Livewire::actingAs($u)->test(X::class, [...])->set()->call()` y aserción sobre la BD (`assertSame`, `fresh()`), no solo sobre la vista.
- **Autorización:** usuario sin permiso → `->assertForbidden()` y nada escrito.
- **Pertenencia:** ids de otro docente/sílabo/sección pasados a métodos públicos no deben modificar nada.
- **Estado:** acciones bloqueadas cuando el registro no es editable (p. ej. sílabo `submitted`).
- **Estado de selección:** al cambiar el filtro padre o escribir en un buscador, el id dependiente queda vacío.
- **Render:** `assertSee(...)` de la pestaña/sección correcta (`->set('activeTab', ...)` si aplica).
- PDF: `->get(route(...))->assertOk()->assertHeader('content-type','application/pdf')` y, para el contenido, `view('reports.x', [...])->render()`.

## Límites de SQLite
- No existen `CONCAT()` ni `FIELD()`: no ejercitar esas consultas en tests (p. ej. buscar con 1 carácter) y no usarlas en código nuevo; ordenar en PHP.
- `enum` se vuelve `CHECK`: un estado nuevo exige migración también para SQLite.
- Migraciones nuevas: probar `migrate`, `migrate:rollback --step=N` y `migrate` sobre un SQLite temporal. Con MySQL disponible, además `php artisan migrate --pretend`.

## No hacer
- No modificar tests existentes para que pasen sin entender el fallo.
- No dar por verificado un cambio de UI solo con tests: indicar qué falta probar en el navegador.
