<?php

namespace App\Exports;

use App\Models\TeacherAssignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CareerAssignmentsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    protected $periodId;
    protected $careerId;

    /**
     * @param int $periodId Periodo académico (obligatorio)
     * @param int|null $careerId Programa de estudios (null = todos los programas)
     */
    public function __construct($periodId, $careerId = null)
    {
        $this->periodId = $periodId;
        $this->careerId = $careerId;
    }

    public function collection()
    {
        return TeacherAssignment::with(['teacher.user', 'didacticUnit.module.studyPlan.career', 'shift'])
            ->where('academic_period_id', $this->periodId)
            ->when($this->careerId, function ($query) {
                $query->whereHas('didacticUnit.module.studyPlan', fn($q) => $q->where('career_id', $this->careerId));
            })
            ->get()
            // Orden: Programa > Semestre > Curso > Sección
            ->sortBy([
                fn($a, $b) => strcmp($a->didacticUnit?->module?->studyPlan?->career?->name ?? '', $b->didacticUnit?->module?->studyPlan?->career?->name ?? ''),
                fn($a, $b) => ($a->didacticUnit?->semester ?? 0) <=> ($b->didacticUnit?->semester ?? 0),
                fn($a, $b) => strcmp($a->didacticUnit?->name ?? '', $b->didacticUnit?->name ?? ''),
                fn($a, $b) => strcmp(strtoupper($a->section), strtoupper($b->section)),
            ])
            ->values();
    }

    public function headings(): array
    {
        return [
            'PROGRAMA DE ESTUDIOS',
            'DOCENTE',
            'UNIDAD DIDÁCTICA',
            'CRÉDITOS',
            'HORAS SEMANALES',
            'SEMESTRE',
            'TURNO / SECCIÓN',
        ];
    }

    public function map($assignment): array
    {
        $user = $assignment->teacher?->user;
        $unit = $assignment->didacticUnit;

        return [
            $unit?->module?->studyPlan?->career?->name ?? '-',
            $user ? trim($user->name . ' ' . $user->lastname) : '-',
            $unit?->name ?? '-',
            $unit?->credits ?? '-',
            $unit?->weekly_hours ?? '-',
            $unit?->semester ?? '-',
            ($assignment->shift->name ?? '-') . ' / ' . strtoupper($assignment->section),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para la primera fila (Cabecera)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1F2937'] // Color Gris oscuro de Tailwind (bg-gray-800)
                ]
            ],
        ];
    }

    public function title(): string
    {
        return 'Carga Académica';
    }
}
