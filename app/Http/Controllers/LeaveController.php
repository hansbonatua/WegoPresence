<?php

namespace App\Http\Controllers;

use App\Exceptions\LeaveException;
use App\Http\Requests\ReviewLeaveRequest;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaveService,
    ) {}

    /**
     * Display the leave listing for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = $request->user();

        $status = $request->input('status');

        return Inertia::render('leaves/index', [
            'leaves' => $this->leaveService->paginate($user, [
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
     * Show the form for creating a new leave request.
     */
    public function create(): Response
    {
        $this->authorize('create', LeaveRequest::class);

        return Inertia::render('leaves/create');
    }

    /**
     * Store a newly created leave request.
     */
    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $this->leaveService->create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave request submitted.')]);

        return to_route('leaves.index');
    }

    /**
     * Cancel the pending leave request of the authenticated user.
     */
    public function cancel(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('cancel', $leave);

        try {
            $this->leaveService->cancel($request->user(), $leave);
        } catch (LeaveException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave request cancelled.')]);

        return back();
    }

    /**
     * Approve the leave request.
     */
    public function approve(ReviewLeaveRequest $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        try {
            $this->leaveService->approve(
                $request->user(),
                $leave,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (LeaveException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave request approved.')]);

        return back();
    }

    /**
     * Reject the leave request.
     */
    public function reject(ReviewLeaveRequest $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('reject', $leave);

        try {
            $this->leaveService->reject(
                $request->user(),
                $leave,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (LeaveException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave request rejected.')]);

        return back();
    }
}
