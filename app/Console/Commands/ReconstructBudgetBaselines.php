<?php

namespace App\Console\Commands;

use App\Services\BudgetBaselineService;
use Illuminate\Console\Command;

class ReconstructBudgetBaselines extends Command
{
    protected $signature = 'budget:reconstruct-baselines';

    protected $description = 'Genera la foto del presupuesto original para presupuestos aprobados que no la tienen (asignado actual menos movimientos aprobados).';

    public function handle(BudgetBaselineService $service): int
    {
        $count = $service->reconstructMissing();
        $this->info("Presupuestos reconstruidos: {$count}");

        return self::SUCCESS;
    }
}
