<?php

namespace App\Http\Controllers;

use App\Exceptions\UserRegistrationException;
use App\Http\Requests\RegisterUserRequest;
use App\Models\Office;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * Show the public registration form.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'offices' => Office::query()
                ->where('status', 'active')
                ->orderBy('office_name')
                ->get(['id', 'office_code', 'office_name']),
        ]);
    }

    /**
     * Store the registration as a pending account.
     */
    public function store(RegisterUserRequest $request): RedirectResponse
    {
        try {
            $this->registrationService->register($request->validated());
        } catch (UserRegistrationException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Registration submitted. Your account is waiting for admin approval.')]);

        return to_route('login');
    }
}
