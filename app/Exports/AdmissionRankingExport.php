<?php

namespace App\Exports;

use App\Models\AdmissionModality;
use App\Models\AdmissionOffering;
use App\Models\Applicant;
use App\Models\Career;
use App\Models\Shift;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AdmissionRankingExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected int $modalityId;
    protected int $careerId;
    protected int $shiftId;
    protected AdmissionModality $modality;
    protected Career $career;
    protected Shift $shift;
    protected int $vacancies;

    public function __construct(int $modalityId, int $careerId, int $shiftId)
    {
        $this->modalityId = $modalityId;
        $this->careerId   = $careerId;
        $this->shiftId    = $shiftId;
        $this->modality   = AdmissionModality::findOrFail($modalityId);
        $this->career     = Career::findOrFail($careerId);
        $this->shift      = Shift::findOrFail($shiftId);

        // Total de vacantes activas para este programa + turno
        $this->vacancies = AdmissionOffering::where('career_id', $careerId)
            ->where('shift_id', $shiftId)
            ->where('is_active', true)
            ->sum('vacancies');
    }

    public function collection()
    {
        $applicants = Applicant::with(['user', 'admissionOffering.career', 'admissionOffering.shift'])
            ->where('admission_modality_id', $this->modalityId)
            ->whereHas('admissionOffering', function ($q) {
                $q->where('career_id', $this->careerId)
                    ->where('shift_id', $this->shiftId);
            })
            ->whereNotNull('exam_score')
            ->orderByDesc('exam_score')
            ->get();

        return $applicants->values()->map(function (Applicant $applicant, int $index) {
            $position = $index + 1;
            $admitted = $this->vacancies > 0 && $position <= $this->vacancies;

            return [
                'posicion'         => $position,
                'dni'              => $applicant->user->document_number,
                'apellidos_nombre' => $applicant->user->lastname . ', ' . $applicant->user->name,
                'programa'         => $applicant->admissionOffering->career->name ?? $this->career->name,
                'turno'            => $applicant->admissionOffering->shift->name ?? $this->shift->name,
                'modalidad'        => $this->modality->name,
                'nota'             => number_format((float) $applicant->exam_score, 2),
                'estado'           => $admitted ? 'INGRESÓ' : 'NO INGRESÓ',
            ];
        });
    }

    public function headings(): array
    {
        return [
            ['RANKING DE POSTULANTES — REPORTE DE NOTAS'],
            ['Programa de Estudios: ' . $this->career->name],
            ['Modalidad: ' . $this->modality->name],
            ['Turno: ' . $this->shift->name],
            ['Vacantes disponibles: ' . $this->vacancies],
            [],
            ['N°', 'DNI', 'Apellidos y Nombres', 'Programa de Estudio', 'Turno', 'Modalidad', 'Nota', 'Estado'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Título
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);

        // Info cabecera (ahora son 4 líneas: A2:A5)
        $sheet->getStyle('A2:A5')->applyFromArray([
            'font' => ['bold' => true],
        ]);

        // Cabecera de columnas (ahora en fila 7, columnas A:H)
        $sheet->getStyle('A7:H7')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Datos
        $lastRow = $sheet->getHighestRow();
        if ($lastRow > 7) {
            $sheet->getStyle("A8:H{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);

            // Colorear filas según estado (columna H ahora es "Estado")
            for ($row = 8; $row <= $lastRow; $row++) {
                $estado = $sheet->getCell("H{$row}")->getValue();
                if ($estado === 'INGRESÓ') {
                    $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
                    ]);
                } else {
                    $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                    ]);
                }
            }
        }

        // Centrar columna N° y las columnas Nota/Estado
        $sheet->getStyle("A7:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G7:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function title(): string
    {
        return 'Ranking';
    }
}
