<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * Display the dashboard.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('dashboard', $this->dashboardService->getDashboardData($request->user()));
    }
}
