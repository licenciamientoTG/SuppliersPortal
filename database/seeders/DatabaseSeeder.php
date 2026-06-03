<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserRoleSeeder::class,
            CompanySeeder::class,
            StationSeeder::class,
            CategorySeeder::class,
            CostCenterSeeder::class,
            // AnnualBudgetSeeder::class,
            DepartmentSeeder::class,
            TaxSeeder::class,
            ExpenseCategorySeeder::class,
            BudgetCedulaSeeder::class,
            // SupplierSeeder::class,
            ReceivingLocationSeeder::class,
            ApprovalLevelSeeder::class,
            AuthorizerRoleSeeder::class,
            SatRetencionSeeder::class,
            // QuotationPlannerTestSeeder::class,
        ]);
    }
}
