<?php

namespace App\Http\Controllers;

use App\Exceptions\UserRegistrationException;
use App\Http\Requests\RejectUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * Display a paginated, searchable and sortable listing of users,
     * grouped by status tab. Admins only see users from their own office.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $user = $request->user();

        $status = in_array($request->input('status'), ['pending', 'active', 'rejected'], true)
            ? $request->input('status')
            : 'active';

        $sort = in_array($request->input('sort'), ['nip', 'name', 'join_date'], true)
            ? $request->input('sort')
            : 'name';

        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $users = User::query()
            ->with(['role:id,name', 'office:id,office_code,office_name', 'approvedBy:id,name'])
            ->where('status', $status)
            ->when($user->isAdmin(), fn ($query) => $query->where('office_id', $user->office_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();

                $query->where(function ($query) use ($search) {
                    $query->where('nip', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => $request->input('search', ''),
                'sort' => $sort,
                'direction' => $direction,
                'status' => $status,
            ],
            'counts' => [
                'active' => $this->registrationService->countByStatus($user, 'active'),
                'pending' => $this->registrationService->countByStatus($user, 'pending'),
                'rejected' => $this->registrationService->countByStatus($user, 'rejected'),
            ],
            'can' => [
                'review' => $user->isSuperAdmin() || $user->isAdmin(),
            ],
        ]);
    }

    /**
     * Show the details of a single user, including pending registrations.
     */
    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('users/show', [
            'user' => $user->load(['role:id,name', 'office:id,office_code,office_name', 'approvedBy:id,name']),
            'can' => [
                'activate' => $user->isPending() && $request->user()->can('activate', $user),
                'reject' => $user->isPending() && $request->user()->can('reject', $user),
                'update' => $request->user()->can('update', $user),
            ],
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/create', [
            'roles' => $this->assignableRoles(),
            'offices' => Office::orderBy('office_name')->get(['id', 'office_code', 'office_name']),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        User::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('users/edit', [
            'user' => $user->load(['role:id,name', 'office:id,office_code,office_name']),
            'roles' => $this->assignableRoles(),
            'offices' => Office::orderBy('office_name')->get(['id', 'office_code', 'office_name']),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password'], $data['password_confirmation']);
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    /**
     * Soft delete the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }

    /**
     * Activate a pending registration, generating the user's NIP.
     */
    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('activate', $user);

        try {
            $this->registrationService->activate($user, $request->user());
        } catch (UserRegistrationException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Registration activated. The user can now sign in with their NIP.')]);

        return back();
    }

    /**
     * Reject a pending registration, recording the reason.
     */
    public function reject(RejectUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('reject', $user);

        try {
            $this->registrationService->reject($user, $request->user(), $request->validated()['rejected_reason']);
        } catch (UserRegistrationException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Registration rejected.')]);

        return back();
    }

    /**
     * The roles the authenticated user is allowed to assign.
     */
    private function assignableRoles(): Collection
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return Role::orderBy('name')->get(['id', 'name']);
        }

        return Role::where('name', 'user')->orderBy('name')->get(['id', 'name']);
    }
}
