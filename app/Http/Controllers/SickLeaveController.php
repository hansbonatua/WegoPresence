<?php

namespace App\Http\Controllers;

use App\Exceptions\SickLeaveException;
use App\Http\Requests\ReviewSickLeaveRequest;
use App\Http\Requests\StoreSickLeaveRequest;
use App\Models\SickLeave;
use App\Services\SickLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SickLeaveController extends Controller
{
    public function __construct(
        private readonly SickLeaveService $sickLeaveService,
    ) {}

    /**
     * Display the sick leave listing for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SickLeave::class);

        $user = $request->user();

        $status = $request->input('status');

        return Inertia::render('sick-leaves/index', [
            'sickLeaves' => $this->sickLeaveService->paginate($user, [
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
     * Show the form for creating a new sick leave.
     */
    public function create(): Response
    {
        $this->authorize('create', SickLeave::class);

        return Inertia::render('sick-leaves/create');
    }

    /**
     * Store a newly created sick leave.
     */
    public function store(StoreSickLeaveRequest $request): RedirectResponse
    {
        $this->authorize('create', SickLeave::class);

        $this->sickLeaveService->create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sick leave submitted.')]);

        return to_route('sick-leaves.index');
    }

    /**
     * Cancel the pending sick leave of the authenticated user.
     */
    public function cancel(Request $request, SickLeave $sickLeave): RedirectResponse
    {
        $this->authorize('cancel', $sickLeave);

        try {
            $this->sickLeaveService->cancel($request->user(), $sickLeave);
        } catch (SickLeaveException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sick leave cancelled.')]);

        return back();
    }

    /**
     * Approve the sick leave.
     */
    public function approve(ReviewSickLeaveRequest $request, SickLeave $sickLeave): RedirectResponse
    {
        $this->authorize('approve', $sickLeave);

        try {
            $this->sickLeaveService->approve(
                $request->user(),
                $sickLeave,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (SickLeaveException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sick leave approved.')]);

        return back();
    }

    /**
     * Reject the sick leave.
     */
    public function reject(ReviewSickLeaveRequest $request, SickLeave $sickLeave): RedirectResponse
    {
        $this->authorize('reject', $sickLeave);

        try {
            $this->sickLeaveService->reject(
                $request->user(),
                $sickLeave,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (SickLeaveException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sick leave rejected.')]);

        return back();
    }
}
