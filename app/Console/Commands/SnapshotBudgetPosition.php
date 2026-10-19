<?php

namespace App\Console\Commands;

use App\Services\BudgetPositionSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** RP-01 (punto #6) · Foto diaria de la posición presupuestal para consultar fechas de corte pasadas. */
class SnapshotBudgetPosition extends Command
{
    protected $signature = 'budget:snapshot-position
                            {--date= : Fecha a congelar (Y-m-d); por defecto hoy}';

    protected $description = 'Congela la posición presupuestal del día (RP-01, punto #6)';

    public function handle(BudgetPositionSnapshotService $snapshots): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now(config('app.timezone'));
        $count = $snapshots->capture($date);

        $this->info("Corte del {$date->format('d/m/Y')}. Renglones guardados: {$count}.");

        return self::SUCCESS;
    }
}
