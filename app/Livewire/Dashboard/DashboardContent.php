<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class DashboardContent extends Component
{
    public function render(DashboardService $dashboardService): View
    {
        $user = auth()->user();

        abort_unless($user, 403);

        return view('livewire.dashboard.dashboard-content', [
            'dashboard' => $dashboardService->buildForUser($user),
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.dashboard.dashboard-placeholder');
    }
}
