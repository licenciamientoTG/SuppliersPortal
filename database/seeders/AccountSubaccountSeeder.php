<?php

namespace Database\Seeders;

use App\Services\AccountSubaccountSyncService;
use Illuminate\Database\Seeder;

class AccountSubaccountSeeder extends Seeder
{
    public function run(): void
    {
        app(AccountSubaccountSyncService::class)->syncFromLegacy();
    }
}
