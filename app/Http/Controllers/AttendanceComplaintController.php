<?php

namespace App\Http\Controllers;

use App\Exceptions\AttendanceComplaintException;
use App\Http\Requests\ReviewAttendanceComplaintRequest;
use App\Http\Requests\StoreAttendanceComplaintRequest;
use App\Models\AttendanceComplaint;
use App\Services\AttendanceComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceComplaintController extends Controller
{
    public function __construct(
        private readonly AttendanceComplaintService $complaintService,
    ) {}

    /**
     * Display the complaint listing for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceComplaint::class);

        $user = $request->user();

        $status = $request->input('status');

        return Inertia::render('attendance-complaints/index', [
            'complaints' => $this->complaintService->paginate($user, [
                'search' => $request->input('search'),
                'status' => is_string($status) ? $status : null,
            ]),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => is_string($status) ? $status : '',
            ],
            'can' => [
                'manage' => $user->isSuperAdmin() || $user->isAdmin(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new complaint.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', AttendanceComplaint::class);

        return Inertia::render('attendance-complaints/create', [
            'attendances' => $this->complaintService->attendancesFor($request->user()),
        ]);
    }

    /**
     * Store a newly created complaint.
     */
    public function store(StoreAttendanceComplaintRequest $request): RedirectResponse
    {
        $this->authorize('create', AttendanceComplaint::class);

        try {
            $this->complaintService->create($request->user(), $request->validated());
        } catch (AttendanceComplaintException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance complaint submitted.')]);

        return to_route('attendance-complaints.index');
    }

    /**
     * Cancel the pending complaint of the authenticated user.
     */
    public function cancel(Request $request, AttendanceComplaint $complaint): RedirectResponse
    {
        $this->authorize('cancel', $complaint);

        try {
            $this->complaintService->cancel($request->user(), $complaint);
        } catch (AttendanceComplaintException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance complaint cancelled.')]);

        return back();
    }

    /**
     * Approve the complaint.
     */
    public function approve(ReviewAttendanceComplaintRequest $request, AttendanceComplaint $complaint): RedirectResponse
    {
        $this->authorize('approve', $complaint);

        try {
            $this->complaintService->approve(
                $request->user(),
                $complaint,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (AttendanceComplaintException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance complaint approved.')]);

        return back();
    }

    /**
     * Reject the complaint.
     */
    public function reject(ReviewAttendanceComplaintRequest $request, AttendanceComplaint $complaint): RedirectResponse
    {
        $this->authorize('reject', $complaint);

        try {
            $this->complaintService->reject(
                $request->user(),
                $complaint,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (AttendanceComplaintException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance complaint rejected.')]);

        return back();
    }
}
