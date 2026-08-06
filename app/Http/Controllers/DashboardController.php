<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        return view('dashboard', [
            'dashboard' => $this->dashboardService->buildInitialForUser($user),
        ]);
    }
}
