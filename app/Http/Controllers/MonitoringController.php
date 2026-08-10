<?php

namespace App\Http\Controllers;

use App\Services\MonitoringService;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function __construct(private readonly MonitoringService $monitoring) {}

    public function alerts(): View
    {
        return $this->render('alerts');
    }

    public function operations(): View
    {
        return $this->render('operations');
    }

    public function budget(): View
    {
        return $this->render('budget');
    }

    public function suppliers(): View
    {
        return $this->render('suppliers');
    }

    public function security(): View
    {
        return $this->render('security');
    }

    private function render(string $monitor): View
    {
        return view('monitoring.show', [
            'board' => $this->monitoring->build($monitor, request()->user()),
        ]);
    }
}
