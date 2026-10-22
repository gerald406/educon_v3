---
name: educon-report-export
description: Usar al crear o modificar reportes descargables de EduCON (Excel con maatwebsite/excel o PDF con DomPDF): nóminas, carga académica, horarios, actas, comprobantes, constancias.
---

# Reportes Excel / PDF en EduCON

Ejemplos de referencia:
- Excel desde Livewire: `TeacherAssignmentManager::exportReport()` + `app/Exports/CareerAssignmentsExport.php`.
- Excel/PDF desde controlador: `app/Http/Controllers/ScheduleReportController.php` + `app/Exports/*ScheduleExport.php`.
- PDF de documentos: `VoucherController`, `AdmissionDocumentController`, `Teacher/SyllabusPdfController` con vistas en `resources/views/reports/**` y `resources/views/treasury/**`.

## Excel
- Clase en `app/Exports/<Nombre>Export.php` implementando `FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle` (o `FromQuery` si el volumen es grande).
- Recibir IDs en el constructor (no modelos ni colecciones); hacer el eager loading en `collection()`.
- Encabezados en MAYÚSCULAS y en español. En `map()` usar `?->` / `??` para relaciones opcionales (planes o usuarios borrados).
- Estados traducidos a texto legible (`active` → `Activo`).
- Desde Livewire: validar filtros requeridos y avisar con `dispatch('swal', ['icon'=>'warning', ...])` antes de `return Excel::download(...)`; llamar `$this->authorize()` primero.
- Nombre de archivo con `Str::slug()` (no `str_replace` manual: rompe con tildes).

## PDF (DomPDF)
- Controlador valida con `$request->validate()` y usa `findOrFail`.
- Vista en `resources/views/reports/<módulo>/`: CSS inline / `<style>` simple (DomPDF no soporta Tailwind ni flex/grid), tablas para el layout, imágenes con ruta absoluta (`public_path()`) o base64.
- `Pdf::loadView(...)->setPaper('a4', 'portrait'|'landscape')->download|stream(...)`.
- Rutas de descarga deben ir en un grupo protegido por `permission:` y, si el recurso pertenece a un usuario (constancia, comprobante), comprobar que le pertenece o que tiene el permiso administrativo.

## Verificación
Generar el archivo con datos reales de la BD local y abrirlo; revisar tildes/ñ, totales y que no haya consultas N+1 (`DB::enableQueryLog()` o Pail).
