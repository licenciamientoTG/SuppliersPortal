<?php

namespace App\Console\Commands;

use App\Models\ApprovalDelegation;
use Illuminate\Console\Command;

class ExpireApprovalDelegations extends Command
{
    protected $signature = 'approval-delegations:expire';

    protected $description = 'Finaliza los periodos de delegación cuya fecha de término ya venció';

    public function handle(): int
    {
        $count = ApprovalDelegation::query()
            ->where('status', 'ACTIVE')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update([
                'status' => 'ENDED',
                'deactivated_at' => now(),
                'deactivation_reason' => 'Finalización automática por fecha programada.',
                'updated_at' => now(),
            ]);

        $this->info("Delegaciones finalizadas: {$count}");

        return self::SUCCESS;
    }
}
