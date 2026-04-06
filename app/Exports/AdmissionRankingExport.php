<?php

namespace App\Exports;

use App\Models\AdmissionModality;
use App\Models\AdmissionOffering;
use App\Models\Applicant;
use App\Models\Career;
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
    protected AdmissionModality $modality;
    protected Career $career;
    protected int $vacancies;

    public function __construct(int $modalityId, int $careerId)
    {
        $this->modalityId = $modalityId;
        $this->careerId   = $careerId;
        $this->modality   = AdmissionModality::findOrFail($modalityId);
        $this->career     = Career::findOrFail($careerId);

        // Total de vacantes activas para este programa
        $this->vacancies = AdmissionOffering::where('career_id', $careerId)
            ->where('is_active', true)
            ->sum('vacancies');
    }

    public function collection()
    {
        $applicants = Applicant::with(['user', 'admissionOffering.career'])
            ->where('admission_modality_id', $this->modalityId)
            ->whereHas('admissionOffering', fn($q) => $q->where('career_id', $this->careerId))
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
            ['Vacantes disponibles: ' . $this->vacancies],
            [],
            ['N°', 'DNI', 'Apellidos y Nombres', 'Programa de Estudio', 'Modalidad', 'Nota', 'Estado'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Título
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);

        // Info cabecera
        $sheet->getStyle('A2:A4')->applyFromArray([
            'font' => ['bold' => true],
        ]);

        // Cabecera de columnas (fila 6)
        $sheet->getStyle('A6:G6')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Datos
        $lastRow = $sheet->getHighestRow();
        if ($lastRow > 6) {
            $sheet->getStyle("A7:G{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);

            // Colorear filas de postulantes que ingresaron (verde claro)
            $dataRows = $lastRow - 6; // rows desde fila 7
            for ($row = 7; $row <= $lastRow; $row++) {
                $estado = $sheet->getCell("G{$row}")->getValue();
                if ($estado === 'INGRESÓ') {
                    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
                    ]);
                } else {
                    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                    ]);
                }
            }
        }

        // Centrar columnas N° y Nota
        $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F6:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function title(): string
    {
        return 'Ranking';
    }
}
