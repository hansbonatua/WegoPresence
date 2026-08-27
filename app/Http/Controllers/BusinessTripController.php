<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessTripException;
use App\Http\Requests\ReviewBusinessTripRequest;
use App\Http\Requests\StoreBusinessTripRequest;
use App\Models\BusinessTrip;
use App\Services\BusinessTripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessTripController extends Controller
{
    public function __construct(
        private readonly BusinessTripService $businessTripService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BusinessTrip::class);
        $user = $request->user();
        $status = $request->input('status');

        return Inertia::render('business-trips/index', [
            'businessTrips' => $this->businessTripService->paginate($user, [
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

    public function create(): Response
    {
        $this->authorize('create', BusinessTrip::class);

        return Inertia::render('business-trips/create');
    }

    public function store(StoreBusinessTripRequest $request): RedirectResponse
    {
        $this->authorize('create', BusinessTrip::class);

        try {
            $this->businessTripService->create($request->user(), $request->validated());
        } catch (BusinessTripException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business trip request submitted.')]);

        return to_route('business-trips.index');
    }

    public function cancel(Request $request, BusinessTrip $businessTrip): RedirectResponse
    {
        $this->authorize('cancel', $businessTrip);

        try {
            $this->businessTripService->cancel($request->user(), $businessTrip);
        } catch (BusinessTripException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business trip request cancelled.')]);

        return back();
    }

    public function approve(ReviewBusinessTripRequest $request, BusinessTrip $businessTrip): RedirectResponse
    {
        $this->authorize('approve', $businessTrip);

        try {
            $this->businessTripService->approve(
                $request->user(),
                $businessTrip,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (BusinessTripException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business trip approved.')]);

        return back();
    }

    public function reject(ReviewBusinessTripRequest $request, BusinessTrip $businessTrip): RedirectResponse
    {
        $this->authorize('reject', $businessTrip);

        try {
            $this->businessTripService->reject(
                $request->user(),
                $businessTrip,
                $request->validated()['approval_notes'] ?? null,
            );
        } catch (BusinessTripException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business trip rejected.')]);

        return back();
    }
}
