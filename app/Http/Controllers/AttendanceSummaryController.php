<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceSummaryRequest;
use App\Http\Requests\ExportAttendanceSummaryRequest;
use App\Models\AttendanceSummary;
use App\Services\AttendanceSummaryExportService;
use App\Services\AttendanceSummaryService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceSummaryController extends Controller
{
    public function __construct(
        private readonly AttendanceSummaryService $summaryService,
        private readonly AttendanceSummaryExportService $summaryExportService,
    ) {}

    /**
     * Display the HR attendance matrix for the admin's office.
     */
    public function index(AttendanceSummaryRequest $request): Response
    {
        $this->authorize('view', AttendanceSummary::class);

        $filters = $request->validated();

        $startDate = filled($filters['start_date'] ?? null)
            ? CarbonImmutable::parse($filters['start_date'])
            : now()->startOfMonth();

        $endDate = filled($filters['end_date'] ?? null)
            ? CarbonImmutable::parse($filters['end_date'])
            : now();

        $data = $this->summaryService->getSummary($request->user(), $startDate, $endDate);

        return Inertia::render('attendance/summary', [
            'users' => $data['users'],
            'dates' => $data['dates'],
            'summary' => $data['summary'],
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Export the attendance summary matrix as an Excel file, scoped to
     * the authenticated admin's own office and the active date range.
     */
    public function export(ExportAttendanceSummaryRequest $request): StreamedResponse
    {
        $this->authorize('export', AttendanceSummary::class);

        $filters = $request->validated();

        return $this->summaryExportService->export(
            $request->user(),
            CarbonImmutable::parse($filters['start_date']),
            CarbonImmutable::parse($filters['end_date']),
        );
    }
}
