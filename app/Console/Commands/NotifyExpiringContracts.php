<?php

namespace App\Console\Commands;

use App\Enum\ContractStatus;
use App\Models\Contract;
use App\Models\ContractExpiryNotification;
use App\Models\User;
use App\Notifications\ContractExpiringNotification;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class NotifyExpiringContracts extends Command
{
    private const MILESTONES = [30, 15, 1];

    protected $signature = 'contracts:notify-expiring';

    protected $description = 'Avisa a compradores y superadmins de contratos a 30, 15 y 1 día de vencer.';

    public function handle(): int
    {
        $today = now()->startOfDay();

        $contracts = Contract::query()
            ->with(['supplier', 'company'])
            ->where('status', ContractStatus::ACTIVE->value)
            ->whereDate('end_date', '>=', $today->copy()->addDay())
            ->whereDate('end_date', '<=', $today->copy()->addDays(max(self::MILESTONES)))
            ->get()
            ->filter(fn (Contract $c) => in_array($c->days_to_expiry, self::MILESTONES, true));

        if ($contracts->isEmpty()) {
            $this->info('Sin contratos en hitos de vencimiento.');

            return self::SUCCESS;
        }

        $roles = Role::query()->whereIn('name', ['buyer', 'superadmin'])->get();
        $recipients = $roles->isEmpty() ? collect() : User::role($roles)->get();

        $notified = 0;
        foreach ($contracts as $contract) {
            $notice = ContractExpiryNotification::firstOrCreate([
                'contract_id'    => $contract->id,
                'milestone_days' => $contract->days_to_expiry,
            ]);

            if (! $notice->wasRecentlyCreated) {
                continue;
            }

            $recipients->each->notify(new ContractExpiringNotification($contract, $contract->days_to_expiry));
            $notified++;
        }

        $this->info("Contratos notificados: {$notified}.");

        return self::SUCCESS;
    }
}
