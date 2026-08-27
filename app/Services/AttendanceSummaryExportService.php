<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceSummaryExportService
{
    private const TITLE = 'WEGOPRESENCE';

    private const SUBTITLE = 'Attendance Summary';

    private const HEADER_ROW = 6;

    private const IDENTITY_COLUMNS = 4;

    /**
     * @var array<int, array{status: string, label: string}>
     */
    private const LEGEND = [
        ['status' => 'H', 'label' => 'Hadir'],
        ['status' => 'A', 'label' => 'Absen'],
        ['status' => 'D', 'label' => 'Dinas'],
        ['status' => 'S', 'label' => 'Sakit'],
        ['status' => 'C', 'label' => 'Cuti'],
        ['status' => 'I', 'label' => 'Izin'],
    ];

    public function __construct(
        private readonly AttendanceSummaryService $summaryService,
    ) {}

    /**
     * Stream the attendance summary matrix as an Excel file. The data
     * comes from the same service that powers the summary page, so the
     * exported statuses always match what is shown on screen.
     */
    public function export(User $admin, CarbonImmutable $startDate, CarbonImmutable $endDate): StreamedResponse
    {
        $data = $this->summaryService->getSummary($admin, $startDate, $endDate);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Summary');

        $this->writeHeader($sheet, $admin, $startDate, $endDate);
        $lastDataRow = $this->writeTable($sheet, $data['users'], $data['dates']);
        $this->writeLegend($sheet, $lastDataRow);

        $this->styleTable($sheet, $startDate, $endDate, $lastDataRow);

        $lastColumn = self::IDENTITY_COLUMNS + count($data['dates']);
        $sheet->setAutoFilter(Coordinate::stringFromColumnIndex(1).self::HEADER_ROW.':'.Coordinate::stringFromColumnIndex($lastColumn).$lastDataRow);

        $sheet->freezePane(Coordinate::stringFromColumnIndex(self::IDENTITY_COLUMNS + 1).(self::HEADER_ROW + 1));

        $writer = new Xlsx($spreadsheet);

        return Response::streamDownload(
            fn () => $writer->save('php://output'),
            'attendance-summary-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  array<int, array{nip: string, name: string, position: string, dates: array<string, string>}>  $users
     * @param  array<int, string>  $dates
     */
    private function writeTable(Worksheet $sheet, array $users, array $dates): int
    {
        $headerRow = [];
        $headerRow[] = 'No';
        $headerRow[] = 'NIP';
        $headerRow[] = 'Name';
        $headerRow[] = 'Position';

        foreach ($dates as $date) {
            $headerRow[] = CarbonImmutable::parse($date)->format('d M');
        }

        $sheet->fromArray($headerRow, null, 'A'.self::HEADER_ROW);

        foreach ($users as $index => $user) {
            $row = self::HEADER_ROW + 1 + $index;
            $values = [$index + 1, $user['nip'], $user['name'], $user['position']];

            foreach ($dates as $date) {
                $values[] = $user['dates'][$date] ?? 'A';
            }

            $sheet->fromArray($values, null, 'A'.$row);
        }

        return self::HEADER_ROW + max(1, count($users));
    }

    private function writeHeader(Worksheet $sheet, User $admin, CarbonImmutable $startDate, CarbonImmutable $endDate): void
    {
        $sheet->setCellValue('A1', self::TITLE);
        $sheet->setCellValue('A2', self::SUBTITLE);
        $sheet->setCellValue('A3', 'Period: '.$startDate->format('d F Y').' - '.$endDate->format('d F Y'));
        $sheet->setCellValue('A4', 'Office: '.($admin->office?->office_name ?? '-'));

        $sheet->getStyle('A1')->getFont()
            ->setBold(true)
            ->setSize(16);

        $sheet->getStyle('A2')->getFont()
            ->setBold(true)
            ->setSize(12);
    }

    private function writeLegend(Worksheet $sheet, int $firstLegendRow): void
    {
        $row = $firstLegendRow + 2;

        $sheet->setCellValue('A'.$row, 'Legend:');

        foreach (self::LEGEND as $index => $item) {
            $targetRow = $row + 1 + $index;
            $sheet->setCellValue('A'.$targetRow, $item['status'].' = '.$item['label']);
        }
    }

    /**
     * @param  array<int, string>  $dates
     */
    private function styleTable(Worksheet $sheet, CarbonImmutable $startDate, CarbonImmutable $endDate, int $lastDataRow): void
    {
        $lastColumn = self::IDENTITY_COLUMNS + $this->dateCount($startDate, $endDate);

        $sheet->getStyle('A'.self::HEADER_ROW.':'.Coordinate::stringFromColumnIndex($lastColumn).$lastDataRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle('A'.self::HEADER_ROW.':'.Coordinate::stringFromColumnIndex($lastColumn).self::HEADER_ROW)
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB('FFFFFFFF');

        $sheet->getStyle('A'.self::HEADER_ROW.':'.Coordinate::stringFromColumnIndex($lastColumn).self::HEADER_ROW)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF0369A1');

        $sheet->getStyle('A'.self::HEADER_ROW.':'.Coordinate::stringFromColumnIndex($lastColumn).$lastDataRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(26);
        $sheet->getColumnDimension('D')->setWidth(20);

        for ($column = self::IDENTITY_COLUMNS + 1; $column <= $lastColumn; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(9);
        }

        $sheet->getStyle('A'.self::HEADER_ROW.':D'.$lastDataRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function dateCount(CarbonImmutable $startDate, CarbonImmutable $endDate): int
    {
        return $startDate->diffInDays($endDate) + 1;
    }
}
