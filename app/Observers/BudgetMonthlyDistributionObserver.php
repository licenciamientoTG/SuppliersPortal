<?php

namespace App\Observers;

use App\Models\BudgetMonthlyDistribution;
use App\Services\BudgetAlertService;
use Illuminate\Support\Facades\DB;

class BudgetMonthlyDistributionObserver
{
    public function created(BudgetMonthlyDistribution $distribution): void
    {
        $this->capture($distribution, true);
    }

    public function updated(BudgetMonthlyDistribution $distribution): void
    {
        if ($distribution->wasChanged(['assigned_amount', 'consumed_amount', 'committed_amount'])) {
            $this->capture($distribution, false);
        }
    }

    private function capture(BudgetMonthlyDistribution $distribution, bool $initial): void
    {
        DB::afterCommit(function () use ($distribution, $initial) {
            $distribution->refresh();
            DB::table('budget_line_history')->insert([
                'budget_monthly_distribution_id' => $distribution->id,
                'assigned_amount' => $distribution->assigned_amount,
                'consumed_amount' => $distribution->consumed_amount,
                'committed_amount' => $distribution->committed_amount,
                'captured_on' => now()->toDateString(),
                'source' => $initial ? 'initial_capture' : 'distribution_change',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(BudgetAlertService::class)->recordThresholdCrossings($distribution);
        });
    }
}
