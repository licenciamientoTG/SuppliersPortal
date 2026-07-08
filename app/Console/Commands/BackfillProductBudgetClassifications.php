<?php

namespace App\Console\Commands;

use App\Services\ProductBudgetClassificationService;
use Illuminate\Console\Command;

class BackfillProductBudgetClassifications extends Command
{
    protected $signature = 'products:backfill-budget-classifications';

    protected $description = 'Assign deterministic budget subaccounts and accounting numbers to incomplete products.';

    public function handle(ProductBudgetClassificationService $service): int
    {
        $stats = $service->backfillIncompleteProducts();

        foreach ($stats as $key => $value) {
            $this->line("{$key}: {$value}");
        }

        return self::SUCCESS;
    }
}
