---
name: educon-ux
description: Usar al crear o modificar vistas Blade/Livewire de EduCON (formularios, buscadores, selects encadenados, modales, grillas, acciones destructivas) para que la interfaz sea consistente, clara para personal administrativo y docentes, y no permita guardar datos en el registro equivocado.
---

# Experiencia de usuario en EduCON

Usuarios: personal administrativo, coordinadores y docentes, muchos con poca experiencia técnica y conexiones lentas. Todo en **español**, mensajes concretos.

## Formularios y selección
- **Selects/buscadores encadenados** (programa → curso → sección): al cambiar un nivel, limpiar todo lo que depende de él (ids, resultados, registros cargados y el formulario). Mostrar siempre qué está seleccionado (ej.: "Curso · Sección A · Docente" sobre el formulario).
- **Buscador predictivo**: al escribir se limpia el id elegido; se elige con clic (`selectX($id)`, solo id). Si se guarda sin elegir, debe fallar la validación, no reutilizar la selección anterior.
- Validación con `x-input-error` junto al campo; reglas en español cuando el mensaje por defecto no se entiende.
- Campos no editables por estado: `@disabled(...)` / `fieldset disabled` **y** guardia en servidor (ver `educon-security-review`). Explicar por qué está bloqueado (banner con el estado).

## Feedback
- Éxito/error con `$this->dispatch('swal', [...])`. El texto dice **qué pasó y qué hacer**: en cruces, nombrar con qué choca ("Matemática - Sección A (08:00 a 10:00)"), no solo "Conflicto".
- Botones que llaman al servidor: `wire:loading.attr="disabled"` + `wire:target`, para evitar dobles envíos.
- Acciones destructivas: `swal:confirm` con las consecuencias explícitas (qué más se borra en cascada). `wire:confirm` solo para acciones menores.
- Indicadores de avance/límites visibles (ej.: "Semanas programadas 12 / 16", "Horas programadas 4 h / 6 h de la UD"); avisar en rojo antes de bloquear cuando la regla no es estricta.

## Listas y grillas
- `wire:key` único en cada elemento de un `@foreach` que se actualiza con Livewire.
- Estado vacío con instrucción del siguiente paso ("Seleccione Programa, Semestre y Turno…").
- Paginación con `resetPage()` al cambiar búsqueda o filtros.
- Edición en lugar de "borrar y volver a crear" cuando el registro es pequeño (ej.: bloques de horario).

## Rendimiento percibido
- Evitar una petición al servidor por tecla: `wire:model.live.debounce.300ms` en buscadores; editores ricos (CKEditor) deben sincronizar con debounce o al guardar, no con `@this.call` en cada cambio.
- Cargar relaciones con `with([...])` para que la vista no haga N+1.

## Consistencia visual
- Copiar estructura y clases de una vista vecina del mismo módulo; Tailwind sin CSS nuevo.
- Colores semánticos: verde éxito/aprobado, amarillo/ámbar revisión o edición, rojo error/destructivo, gris borrador/deshabilitado.
- Probar en ancho móvil las tablas (`overflow-x-auto`).

## Verificar
- Recorrer el flujo en el navegador como el rol real (no solo admin): elegir, cambiar de selección a mitad del flujo, guardar, editar, cancelar.
