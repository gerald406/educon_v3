---
name: educon-security-review
description: Usar al escribir o revisar componentes Livewire, controladores o rutas de EduCON que lean o modifiquen datos de docentes, estudiantes, sílabos, notas, horarios o carga académica; y al revisar un diff antes de commit. Checklist de autorización, pertenencia (IDOR), estado y datos sensibles.
---

# Revisión de seguridad en EduCON

Todo método público de un componente Livewire es **invocable desde el navegador con cualquier argumento**. Ocultar un botón no protege nada.

## Checklist (aplicar a cada acción que lee o muta)
1. **Permiso**: ruta con `permission:`/`role:` **y** `$this->authorize('<slug>')` en la acción, incluidos listeners `#[On]` (ver `educon-permission`).
2. **Pertenencia (IDOR)**: en rutas anidadas `{padre}/{hijo}` verificar que el hijo pertenece al padre (ej.: `LearningSession::unitBelongsToSyllabus()`), y que el padre pertenece al usuario (`teacher_id === auth()->user()->teacher->id`). Responder `abort(404)` si no pertenece.
3. **Ids del cliente**: volver a resolver en servidor con las restricciones de contexto (período activo, curso seleccionado, sílabo propio) — ej.: `ScheduleManager::findSelectableAssignment()`, `SyllabusUnit::whereHas('indicator', syllabus_id)`. Nunca `Model::find($idDelFormulario)` sin filtrar.
4. **Estado**: si el registro tiene flujo (`draft/submitted/approved/observed`), validar el estado **leyéndolo de la BD** antes de mutar (patrón `SyllabusEditor::ensureEditable()`).
5. **Lista blanca** para valores que vienen del cliente y no son texto libre: estados, nombres de campo, instrumentos, días (`in_array(..., true)` / regla `in:`). Ej.: `updateUnitField()` permite solo 4 campos.
6. **Alcance**: una acción sobre un registro no debe modificar datos maestros compartidos (carrera, plan, módulo, UD, turno, usuario). Si hace falta, va en su propio módulo con su permiso.
7. **Rol sin perfil**: Administrador/Coordinador pueden no tener `teacher`: usar `auth()->user()->teacher?->id` y manejar null.
8. **Consultas por período**: validaciones de cruces/duplicados limitadas al período correspondiente (antes el aula chocaba con períodos pasados).
9. **Acciones destructivas o en cascada**: confirmación (`swal:confirm`) + `authorize` + mensaje con las consecuencias.
10. **Salida HTML**: `{!! !!}` solo para HTML de CKEditor ya existente; nunca para datos de usuario sin sanitizar. En atributos `wire:click` no interpolar texto (`'{{ $nombre }}'`): pasar solo ids.
11. **SQL crudo**: siempre con bindings (`whereRaw('... LIKE ?', [$v])`).
12. **Datos personales**: no leer completos ni publicar `*.sql`, `educon.zip`, `codigo_completo.txt`; para análisis, solo agregados.

## Verificar
- Un test por cada regla aplicable (sin permiso → `assertForbidden`; id ajeno → sin cambios; estado bloqueado → sin cambios). Ver `educon-testing`.
- Para revisar un diff completo, recorrer la checklist por archivo y reportar: archivo:línea, regla, escenario concreto de abuso, corrección.

## No hacer
- No confiar en `wire:model` de ids, `disabled` en inputs ni `@if` en la vista como control de acceso.
- No mostrar `$e->getMessage()` de excepciones de BD al usuario en código nuevo: mensaje genérico + `report($e)`.
