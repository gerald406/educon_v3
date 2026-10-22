---
name: educon-permission
description: Usar al agregar, renombrar o reasignar un permiso o rol (spatie/laravel-permission) en EduCON, o al proteger una ruta/acción nueva. Evita que el permiso quede desincronizado entre seeder, rutas, componentes y menú.
---

# Permisos y roles en EduCON

Los permisos viven en 5 lugares. Un cambio incompleto produce menús visibles que dan 403, o páginas accesibles sin menú.

| # | Lugar | Qué hacer |
|---|-------|-----------|
| 1 | `database/seeders/RolesAndPermissionsSeeder.php` | `Permission::firstOrCreate(['name' => '<slug>'])` en la sección del módulo y asignarlo a los roles correspondientes. Es la fuente de verdad. |
| 2 | `routes/web.php` | `->middleware('permission:<slug>')` en la ruta o en el grupo. |
| 3 | Componente Livewire | `$this->authorize('<slug>')` en cada acción mutadora y en los `#[On]`. |
| 4 | `resources/views/livewire/layout/sidebar.blade.php` | `@can('<slug>')` alrededor del enlace; añadir el slug al `@canany` del bloque del módulo. |
| 5 | `app/Models/User.php::hasAdminAccess()` **y** `app/Livewire/Layout/Sidebar.php::render()` | Si es un permiso de personal administrativo que debe abrir el sidebar, añadirlo a **ambas** listas (están duplicadas). |

## Convenciones
- Slug en español, minúsculas, con guiones: `gestionar-<recurso>`, `registrar-<acción>`, `ver-<reporte>`, `aprobar-<algo>`.
- Roles existentes: `Administrador`, `Coordinador`, `Docente`, `Estudiante`, `Secretario Academico`, `Tesoreria`, `Admisión`, `Externo`. No crear roles nuevos sin confirmarlo con el usuario.
- `Docente`/`Coordinador` nunca ven el sidebar: sus enlaces van en la navegación superior (`navigation-menu.blade.php`).

## Despliegue
En producción el permiso no existe hasta ejecutar:
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
```
Indicarlo siempre al usuario en el resumen del cambio. Nunca correr `db:seed` completo en producción (crea/actualiza el usuario admin).
