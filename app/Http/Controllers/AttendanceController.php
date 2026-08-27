<?php

namespace App\Http\Controllers;

use App\Exceptions\AttendanceException;
use App\Http\Requests\ExportAttendanceRecapRequest;
use App\Http\Requests\RecapAttendanceRequest;
use App\Http\Requests\StoreAttendanceCheckoutRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\Office;
use App\Services\AttendanceExportService;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceExportService $attendanceExportService,
    ) {}

    /**
     * Display the attendance page for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('attendance/index', [
            'today' => $this->attendanceService->todayFor($user),
            'history' => $this->attendanceService->historyFor($user, 5),
        ]);
    }

    /**
     * Display the paginated attendance recap for managers.
     */
    public function recap(RecapAttendanceRequest $request): Response
    {
        $this->authorize('recap', Attendance::class);

        $filters = $request->validated();

        return Inertia::render('attendance/recap', [
            'recaps' => $this->attendanceService->getAttendanceRecap(
                $request->user(),
                $filters,
            ),
            'filters' => [
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
                'search' => $filters['search'] ?? '',
                'office_id' => isset($filters['office_id']) ? (string) $filters['office_id'] : '',
                'attendance_status' => $filters['attendance_status'] ?? '',
            ],
            'offices' => Office::orderBy('office_name')
                ->get(['id', 'office_code', 'office_name', 'city']),
        ]);
    }

    /**
     * Export the attendance recap as an Excel file.
     */
    public function exportExcel(ExportAttendanceRecapRequest $request): StreamedResponse
    {
        $this->authorize('export', Attendance::class);

        return $this->attendanceExportService->exportExcel(
            $request->user(),
            $request->validated(),
        );
    }

    /**
     * Export the attendance recap as a PDF file.
     */
    public function exportPdf(ExportAttendanceRecapRequest $request): StreamedResponse
    {
        $this->authorize('export', Attendance::class);

        return $this->attendanceExportService->exportPdf(
            $request->user(),
            $request->validated(),
        );
    }

    /**
     * Handle a check-in.
     */
    public function checkIn(StoreAttendanceRequest $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        try {
            $attendance = $this->attendanceService->checkIn(
                $request->user(),
                $request->validated(),
            );
        } catch (AttendanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Checked in at '.$attendance->check_in_time?->format('H:i').'.',
        );
    }

    /**
     * Handle a check-out.
     */
    public function checkOut(StoreAttendanceCheckoutRequest $request): RedirectResponse
    {
        $attendance = $this->attendanceService->todayFor($request->user());

        if ($attendance === null) {
            return back()->with('error', 'You have not checked in today.');
        }

        $this->authorize('update', $attendance);

        try {
            $this->attendanceService->checkOut(
                $request->user(),
                $request->validated('photo'),
            );
        } catch (AttendanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Checked out successfully.');
    }
}
