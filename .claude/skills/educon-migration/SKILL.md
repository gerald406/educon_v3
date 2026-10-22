---
name: educon-migration
description: Usar al crear o modificar migraciones, modelos o índices en EduCON. La BD de producción es MySQL con datos reales; los tests corren en SQLite en memoria.
---

# Migraciones seguras en EduCON

## Reglas
1. **Nunca editar una migración ya ejecutada en producción.** Crear una nueva (`add_<col>_to_<tabla>_table`, `add_indexes_to_<tabla>`). Las migraciones existentes hasta `create_survey_answers_table` se asumen desplegadas.
2. Columnas nuevas en tablas con datos: `->nullable()` o `->default(...)`. Si debe ser obligatoria, migración en dos pasos (agregar nullable + backfill → luego `->change()`).
3. Claves foráneas: `foreignId('x_id')->constrained()` con `->cascadeOnDelete()` / `->nullOnDelete()` / `->restrictOnDelete()` decidido explícitamente según si el hijo tiene sentido sin el padre (notas, matrículas y pagos: `restrict`).
4. Compatibilidad con SQLite (tests): `fullText`, `ENUM` modificados con `DB::statement`, `CONCAT` o `->change()` sobre enums deben ir protegidos:
   ```php
   if (DB::getDriverName() === 'mysql') { ... }
   ```
5. `down()` siempre implementado y reversible.
6. Nombres: tablas en inglés plural snake_case; estados como `string` con valores en inglés (`active`, `inactive`, …), no `enum` nativos nuevos.

## Modelo
- `$fillable` explícito, `casts()` para fechas/decimales/booleanos, relaciones con tipo de retorno (`BelongsTo`, `HasMany`…), scopes `scopeActive()` donde exista `status`.
- Si hay factory, actualizarla para la columna nueva.

## Verificación
```bash
php artisan migrate --pretend          # revisar el SQL generado para MySQL
php artisan migrate && php artisan migrate:rollback --step=1 && php artisan migrate
php artisan test                       # SQLite
```
Avisar al usuario del comando de despliegue (`php artisan migrate --force`) y de si requiere backup previo.
