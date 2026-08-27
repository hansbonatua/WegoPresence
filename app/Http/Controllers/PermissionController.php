<?php

namespace App\Http\Controllers;

use App\Exceptions\PermissionException;
use App\Http\Requests\ReviewPermissionRequest;
use App\Http\Requests\StorePermissionRequest;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Display the permission listing for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Permission::class);

        $user = $request->user();

        $status = $request->input('status');

        return Inertia::render('permissions/index', [
            'permissions' => $this->permissionService->paginate($user, [
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
     * Show the form for creating a new permission request.
     */
    public function create(): Response
    {
        $this->authorize('create', Permission::class);

        return Inertia::render('permissions/create');
    }

    /**
     * Store a newly created permission request.
     */
    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        $this->permissionService->create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission request submitted.')]);

        return to_route('permissions.index');
    }

    /**
     * Cancel the pending permission request of the authenticated user.
     */
    public function cancel(Request $request, Permission $permission): RedirectResponse
    {
        $this->authorize('cancel', $permission);

        try {
            $this->permissionService->cancel($request->user(), $permission);
        } catch (PermissionException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission request cancelled.')]);

        return back();
    }

    /**
     * Approve the permission request.
     */
    public function approve(ReviewPermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->authorize('approve', $permission);

        try {
            $this->permissionService->approve(
                $request->user(),
                $permission,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (PermissionException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission approved.')]);

        return back();
    }

    /**
     * Reject the permission request.
     */
    public function reject(ReviewPermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->authorize('reject', $permission);

        try {
            $this->permissionService->reject(
                $request->user(),
                $permission,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (PermissionException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission rejected.')]);

        return back();
    }
}
