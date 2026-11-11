<?php

namespace App\Exports;

use App\Models\Institution;
use App\Models\NonTeachingActivity;
use App\Services\NonTeachingScheduleService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooter;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooterDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * [NUEVO] Horario no lectivo del docente sobre la plantilla oficial
 * (resources/templates/horario_no_lectivo.xlsx, copia vacía de docs/archivos/HORARIO NO LECTIVO.xlsx).
 *
 * Igual que TeacherPersonalScheduleExport, rellena la plantilla con PhpSpreadsheet para
 * conservar encabezado, pie de firmas, formato y fórmulas. Recibe los datos de
 * NonTeachingScheduleService::documentData().
 */
class TeacherNonTeachingScheduleExport
{
    public const TEMPLATE = 'templates/horario_no_lectivo.xlsx';

    private const DAY_COLUMNS = ['monday' => 'C', 'tuesday' => 'D', 'wednesday' => 'E', 'thursday' => 'F', 'friday' => 'G'];

    /** Fila de la franja 08:00 - 09:00; las 8 franjas ocupan las filas 5 a 12. */
    private const GRID_FIRST_ROW = 5;

    private const LECTIVE_FIRST_ROW = 15;

    private const LECTIVE_TEMPLATE_ROWS = 5;

    /** Las 8 actividades oficiales están impresas en las filas 24 a 31 de la plantilla. */
    private const ACTIVITY_FIRST_ROW = 24;

    public function __construct(private array $data, private ?Institution $institution = null) {}

    public function save(string $path): void
    {
        $spreadsheet = $this->spreadsheet();
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    public function spreadsheet(): Spreadsheet
    {
        $spreadsheet = IOFactory::load(resource_path(self::TEMPLATE));
        $sheet = $spreadsheet->getActiveSheet();

        // Copias antiguas del encabezado que la librería conserva sin interpretar
        $unparsed = $spreadsheet->getUnparsedLoadedData();
        foreach (array_keys($unparsed['sheets'] ?? []) as $codeName) {
            unset($unparsed['sheets'][$codeName]['vmlDrawings']);
        }
        $spreadsheet->setUnparsedLoadedData($unparsed);

        $sheet->setCellValue('C3', $this->data['teacher_name']);
        $sheet->setCellValue('F3', $this->data['program']);
        // Un nombre de programa largo no cabe en una línea: letra menor en dos líneas
        if (mb_strlen($this->data['program']) > 34) {
            $sheet->getStyle('F3')->getFont()->setSize(8);
            $sheet->getStyle('F3')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(3)->setRowHeight(25.5);
        }

        $this->fillGrid($sheet);
        // Primero las actividades (más abajo): insertar filas de carga lectiva las desplaza
        $this->fillActivities($sheet);
        $this->fillLective($sheet);
        $this->applyHeader($sheet);

        $sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(1);
        $sheet->getPageMargins()->setBottom(0.75);
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    /** En cada franja de una hora: el nombre de la actividad no lectiva ubicada en ella. */
    private function fillGrid(Worksheet $sheet): void
    {
        $cells = [];
        foreach ($this->data['blocks'] as $block) {
            if (! isset(self::DAY_COLUMNS[$block['day']])) {
                continue;
            }
            for ($minute = $block['start']; $minute < $block['end']; $minute += 30) {
                $index = intdiv($minute - NonTeachingScheduleService::GRID_START, 60);
                if ($index < 0 || $index > 7) {
                    continue;
                }
                $cell = self::DAY_COLUMNS[$block['day']].(self::GRID_FIRST_ROW + $index);
                $cells[$cell][$block['label']] = true;
            }
        }

        $sheet->getStyle('C'.self::GRID_FIRST_ROW.':G'.(self::GRID_FIRST_ROW + 7))->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 8, 'bold' => false],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $linesByRow = [];
        foreach ($cells as $cell => $labels) {
            $text = implode(' / ', array_keys($labels));
            $sheet->setCellValue($cell, $text);
            $row = (int) preg_replace('/\D/', '', $cell);
            $linesByRow[$row] = max($linesByRow[$row] ?? 2, (int) ceil(mb_strlen($text) / 21));
        }
        foreach ($linesByRow as $row => $lines) {
            if ($lines > 2) {
                $sheet->getRowDimension($row)->setRowHeight($lines * 11.5);
            }
        }
    }

    private function fillActivities(Worksheet $sheet): void
    {
        $rowOf = array_flip(array_keys(NonTeachingActivity::ACTIVITIES));
        $first = self::ACTIVITY_FIRST_ROW;

        foreach ($this->data['activities'] as $activity) {
            if (! isset($rowOf[$activity['key']])) {
                continue;
            }
            $row = $first + $rowOf[$activity['key']];
            $sheet->setCellValue("D{$row}", $activity['sub_activity']);
            $sheet->setCellValue("G{$row}", $activity['hours']);
            $sheet->getStyle("D{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

            // La subactividad (hasta 255 caracteres) ocupa D:F: unas 60 letras por línea
            $lines = (int) ceil(mb_strlen($activity['sub_activity']) / 60);
            if ($lines > 1) {
                $sheet->getRowDimension($row)->setRowHeight(max(17.25, $lines * 12));
            }
        }

        $last = $first + count(NonTeachingActivity::ACTIVITIES) - 1;
        // Los nombres largos de actividad quedaban cortados en la plantilla
        $sheet->getStyle("B{$first}:B{$last}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->setCellValue('G'.($last + 1), sprintf('=SUM(G%d:G%d)', $first, $last));
    }

    private function fillLective(Worksheet $sheet): void
    {
        $rows = $this->data['lective_rows'];
        $first = self::LECTIVE_FIRST_ROW;
        $templateLast = $first + self::LECTIVE_TEMPLATE_ROWS - 1;
        $extra = max(0, count($rows) - self::LECTIVE_TEMPLATE_ROWS);

        // Más de 5 cargas: filas nuevas con el formato de la última (desplaza lo que sigue)
        if ($extra > 0) {
            $sheet->insertNewRowBefore($templateLast + 1, $extra);
            for ($row = $templateLast + 1; $row <= $templateLast + $extra; $row++) {
                foreach (range('B', 'G') as $column) {
                    $sheet->duplicateStyle($sheet->getStyle($column.$templateLast), $column.$row);
                }
                $sheet->mergeCells("B{$row}:C{$row}");
                $sheet->mergeCells("D{$row}:E{$row}");
                $sheet->getRowDimension($row)->setRowHeight($sheet->getRowDimension($templateLast)->getRowHeight());
            }
        }

        foreach ($rows as $i => $data) {
            $row = $first + $i;
            $sheet->setCellValue("B{$row}", $data['program']);
            $sheet->setCellValue("D{$row}", $data['unit']);
            $sheet->setCellValue("F{$row}", $data['semester_shift']);
            $sheet->setCellValue("G{$row}", $data['hours']);
            // En la plantilla cada fila de ejemplo tenía una alineación distinta: se unifican
            $sheet->getStyle("B{$row}:E{$row}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1)
                ->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("F{$row}:G{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $lines = max((int) ceil(mb_strlen($data['program']) / 30), (int) ceil(mb_strlen($data['unit']) / 38));
            if ($lines > 1) {
                $sheet->getRowDimension($row)->setRowHeight($lines * 13);
            }
        }

        // Totales (sus filas se desplazan con las filas insertadas)
        $lectiveTotal = $templateLast + $extra + 1;
        $activityFirst = self::ACTIVITY_FIRST_ROW + $extra;
        $activityTotal = $activityFirst + count(NonTeachingActivity::ACTIVITIES);
        $sheet->setCellValue("G{$lectiveTotal}", sprintf('=SUM(G%d:G%d)', $first, $templateLast + $extra));
        $sheet->setCellValue("G{$activityTotal}", sprintf('=SUM(G%d:G%d)', $activityFirst, $activityTotal - 1));
        $sheet->setCellValue('G'.($activityTotal + 1), sprintf('=SUM(G%d,G%d)', $lectiveTotal, $activityTotal));
    }

    /** Título del encabezado con el período y, si existe, la imagen de Configuración → Institución. */
    private function applyHeader(Worksheet $sheet): void
    {
        $headerFooter = $sheet->getHeaderFooter();
        $headerFooter->setOddHeader(preg_replace(
            '/HORARIO DEL DOCENTE.*$/u',
            'HORARIO DEL DOCENTE '.$this->data['period_code'],
            $headerFooter->getOddHeader()
        ));

        $relative = $this->institution?->schedule_header_url;
        if (! $relative || ! Storage::disk('public')->exists($relative)) {
            return;
        }

        $path = Storage::disk('public')->path($relative);
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true) || ! @getimagesize($path)) {
            return;
        }

        $current = $headerFooter->getImages()[HeaderFooter::IMAGE_HEADER_CENTER] ?? null;
        $maxWidth = $current?->getWidth() ?: 648;
        $maxHeight = $current?->getHeight() ?: 74;

        $drawing = new HeaderFooterDrawing;
        $drawing->setName('Encabezado');
        $drawing->setPath($path);
        $drawing->setResizeProportional(true);
        $drawing->setHeight($maxHeight);
        if ($drawing->getWidth() > $maxWidth) {
            $drawing->setWidth($maxWidth);
        }

        $headerFooter->addImage($drawing, HeaderFooter::IMAGE_HEADER_CENTER);
    }
}
