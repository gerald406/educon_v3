<?php

namespace App\Exports;

use App\Models\Institution;
use App\Services\PedagogicalHourService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooter;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooterDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * [NUEVO] Horario personal del docente sobre la plantilla oficial
 * (resources/templates/horario_personal.xlsx, copia vacía de docs/archivos/HOARARIO_PERSONAL.xlsx).
 *
 * No es un export de maatwebsite: rellena la plantilla con PhpSpreadsheet para conservar
 * logos, encabezado, pie de firmas, colores y fórmulas. Recibe los datos ya armados por
 * TeacherPersonalScheduleService::build().
 */
class TeacherPersonalScheduleExport
{
    public const TEMPLATE = 'templates/horario_personal.xlsx';

    /** Columnas de la grilla por día. La de viernes está combinada G:I. */
    private const DAY_COLUMNS = ['monday' => 'C', 'tuesday' => 'D', 'wednesday' => 'E', 'thursday' => 'F', 'friday' => 'G'];

    /** Primera fila de cada jornada en la plantilla. */
    private const GROUP_FIRST_ROW = ['morning' => 7, 'night' => 14];

    private const TABLE_FIRST_ROW = 22;

    private const TABLE_TEMPLATE_ROWS = 8;

    /** Caracteres aproximados por línea (letra de 9-10 pt) en las celdas de la grilla y de la tabla. */
    private const GRID_CHARS_PER_LINE = 30;

    private const PROGRAM_CHARS_PER_LINE = 40;

    private const UNIT_CHARS_PER_LINE = 56;

    public function __construct(private array $data, private ?Institution $institution = null) {}

    /** Genera el archivo en $path. */
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

        // Los dibujos VML que la librería conserva "sin interpretar" son copias antiguas del
        // encabezado: se descartan para no dejar partes huérfanas en el archivo. Los logos
        // del encabezado se vuelven a escribir desde getHeaderFooter()->getImages().
        $unparsed = $spreadsheet->getUnparsedLoadedData();
        foreach (array_keys($unparsed['sheets'] ?? []) as $codeName) {
            unset($unparsed['sheets'][$codeName]['vmlDrawings']);
        }
        $spreadsheet->setUnparsedLoadedData($unparsed);

        $sheet->setCellValue('A1', $this->data['title']);
        $sheet->setCellValue('C3', $this->data['teacher_name']);

        $this->fillGrid($sheet);
        $this->fillTable($sheet);
        $this->applyHeaderImage($sheet);

        // El documento oficial es de una sola página: si las filas crecen (nombres largos o
        // más de 8 cargas) se reduce la escala en lugar de pasar a una segunda hoja.
        // Margen inferior suficiente para que la tabla no se monte sobre el pie de firmas.
        $sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(1);
        $sheet->getPageMargins()->setBottom(0.75);

        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function fillGrid(Worksheet $sheet): void
    {
        // En la plantilla algunas celdas de la grilla tienen otro tamaño de letra o alineación
        // (restos del ejemplo): se unifican sin tocar bordes ni colores.
        foreach (self::GROUP_FIRST_ROW as $group => $firstRow) {
            $lastRow = $firstRow + count(PedagogicalHourService::GROUPS[$group]['slots']) - 1;
            $sheet->getStyle("C{$firstRow}:I{$lastRow}")->applyFromArray([
                'font' => ['name' => 'Calibri', 'size' => 9, 'bold' => false],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
        }

        foreach (self::GROUP_FIRST_ROW as $group => $firstRow) {
            foreach (array_keys(PedagogicalHourService::GROUPS[$group]['slots']) as $index) {
                $row = $firstRow + $index;
                $lines = 2;

                foreach (self::DAY_COLUMNS as $day => $column) {
                    $text = implode(' / ', $this->data['grid'][$group][$index][$day] ?? []);
                    if ($text === '') {
                        continue;
                    }
                    $sheet->setCellValue($column.$row, $text);
                    $lines = max($lines, (int) ceil(mb_strlen($text) / self::GRID_CHARS_PER_LINE));
                }

                // La plantilla da espacio para 2 líneas (25.5 pt): si el texto necesita más, se amplía la fila
                if ($lines > 2) {
                    $sheet->getRowDimension($row)->setRowHeight($lines * 12.75);
                }
            }
        }
    }

    private function fillTable(Worksheet $sheet): void
    {
        $rows = $this->data['rows'];
        $first = self::TABLE_FIRST_ROW;
        $templateLast = $first + self::TABLE_TEMPLATE_ROWS - 1;
        $extra = max(0, count($rows) - self::TABLE_TEMPLATE_ROWS);

        // El encabezado "HORAS SEMANALES" ocupa dos líneas y en la plantilla quedaba cortado
        $sheet->getRowDimension($first - 1)->setRowHeight(25.5);
        $sheet->getStyle('I'.($first - 1))->getFont()->setSize(8);

        // Más de 8 cargas: se insertan filas con el formato de la última fila de la tabla
        if ($extra > 0) {
            $sheet->insertNewRowBefore($templateLast + 1, $extra);
            for ($row = $templateLast + 1; $row <= $templateLast + $extra; $row++) {
                foreach (range('B', 'I') as $column) {
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
            $sheet->setCellValue("G{$row}", $data['theory']);
            $sheet->setCellValue("H{$row}", $data['practice']);
            $sheet->setCellValue("I{$row}", $data['hours']);

            $lines = max(
                (int) ceil(mb_strlen($data['program']) / self::PROGRAM_CHARS_PER_LINE),
                (int) ceil(mb_strlen($data['unit']) / self::UNIT_CHARS_PER_LINE)
            );
            if ($lines > 1) {
                $sheet->getRowDimension($row)->setRowHeight($lines * 13.5);
            }
        }

        $sheet->setCellValue('H3', sprintf('=SUM(I%d:I%d)', $first, $templateLast + $extra));
    }

    /** Encabezado propio de la institución (Configuración → Institución); si no hay, queda el de la plantilla. */
    private function applyHeaderImage(Worksheet $sheet): void
    {
        $relative = $this->institution?->schedule_header_url;
        if (! $relative || ! Storage::disk('public')->exists($relative)) {
            return;
        }

        $path = Storage::disk('public')->path($relative);
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true) || ! @getimagesize($path)) {
            return;
        }

        $current = $sheet->getHeaderFooter()->getImages()[HeaderFooter::IMAGE_HEADER_LEFT] ?? null;
        $maxWidth = $current?->getWidth() ?: 754;
        $maxHeight = $current?->getHeight() ?: 70;

        $drawing = new HeaderFooterDrawing;
        $drawing->setName('Encabezado');
        $drawing->setPath($path);
        $drawing->setResizeProportional(true);
        $drawing->setHeight($maxHeight);
        if ($drawing->getWidth() > $maxWidth) {
            $drawing->setWidth($maxWidth);
        }

        $sheet->getHeaderFooter()->addImage($drawing, HeaderFooter::IMAGE_HEADER_LEFT);
    }
}
