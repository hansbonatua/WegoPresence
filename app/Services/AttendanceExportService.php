<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * Stream an Excel export of the attendance recap.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportExcel(User $user, array $filters = []): StreamedResponse
    {
        $rows = $this->rows($user, $filters);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Recap');

        $sheet->fromArray([
            'No',
            'NIP',
            'Nama',
            'Jabatan',
            'Office',
            'Tanggal',
            'Check In',
            'Check Out',
            'Status',
            'Late Minutes',
            'Latitude',
            'Longitude',
        ], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray([
                $index + 1,
                $row['nip'],
                $row['name'],
                $row['position'],
                $row['office'],
                $row['attendance_date'],
                $row['check_in_time'],
                $row['check_out_time'],
                $row['status'],
                $row['late_minutes'],
                $row['latitude'],
                $row['longitude'],
            ], null, 'A'.($index + 2));
        }

        $writer = new Xlsx($spreadsheet);

        return Response::streamDownload(
            fn () => $writer->save('php://output'),
            $this->filename('xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Stream a PDF export of the attendance recap.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportPdf(User $user, array $filters = []): StreamedResponse
    {
        $rows = $this->rows($user, $filters);

        $html = view('exports.attendance-recap', [
            'rows' => $rows,
            'period' => $this->period($filters),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $this->filename('pdf'), ['Content-Type' => 'application/pdf']);
    }

    /**
     * Map the scoped recap records into export rows.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, string|null>>
     */
    private function rows(User $user, array $filters): Collection
    {
        return $this->attendanceService->recapRecords($user, $filters)->map(
            function (Attendance $attendance): array {
                $recapStatus = AttendanceService::computeRecapStatus($attendance->check_in_time);
                $lateMinutes = AttendanceService::computeRecapLateMinutes($attendance->check_in_time);

                return [
                    'nip' => $attendance->user?->nip ?? '-',
                    'name' => $attendance->user?->name ?? '-',
                    'position' => $attendance->user?->position ?? '-',
                    'office' => $attendance->user?->office?->office_name ?? '-',
                    'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
                    'check_in_time' => $attendance->check_in_time?->format('H:i') ?? '-',
                    'check_out_time' => $attendance->check_out_time?->format('H:i') ?? '-',
                    'status' => $recapStatus === 'late' ? 'Late' : 'On Time',
                    'late_minutes' => $lateMinutes,
                    'latitude' => $attendance->latitude ?? '',
                    'longitude' => $attendance->longitude ?? '',
                ];
            },
        );
    }

    private function filename(string $extension): string
    {
        return 'attendance-recap-'.now()->format('Y-m-d').'.'.$extension;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function period(array $filters): string
    {
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        if (filled($start) && filled($end)) {
            return $start.' - '.$end;
        }

        if (filled($start)) {
            return $start.' - Today';
        }

        if (filled($end)) {
            return 'Until '.$end;
        }

        return 'All time';
    }
}
