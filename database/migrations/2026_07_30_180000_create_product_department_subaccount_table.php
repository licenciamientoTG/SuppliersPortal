<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_department_subaccount', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_service_id')->constrained('products_services')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('subaccounts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_service_id', 'department_id'], 'product_department_unique');
            $table->index(['product_service_id', 'subaccount_id'], 'product_subaccount_mapping_index');
        });

        $this->seedPapereraRubbermaidRule();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_department_subaccount');
    }

    private function seedPapereraRubbermaidRule(): void
    {
        $productId = DB::table('products_services')
            ->where('short_name', 'PAPELERA RUBBERMAID 28 QTS')
            ->value('id');

        if (! $productId) {
            return;
        }

        $subaccounts = DB::table('subaccounts')
            ->whereIn('name', ['Mantenimiento General', 'Limpieza'])
            ->pluck('id', 'name');

        if ($subaccounts->count() !== 2) {
            throw new RuntimeException('No se encontraron las subcuentas requeridas para PAPELERA RUBBERMAID 28 QTS.');
        }

        $departmentIds = [3, 4, 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16, 18, 19, 20, 21, 22, 23];
        if (DB::table('departments')->whereIn('id', $departmentIds)->count() !== count($departmentIds)) {
            throw new RuntimeException('Faltan departamentos requeridos para la regla inicial de PAPELERA RUBBERMAID 28 QTS.');
        }

        $subaccountIds = $subaccounts->values()->all();
        DB::table('product_service_subaccount')->insertOrIgnore(
            collect($subaccountIds)->map(fn (int $subaccountId) => [
                'product_service_id' => $productId,
                'subaccount_id' => $subaccountId,
            ])->all()
        );

        $legacyBudgetCedulaIds = DB::table('subaccounts')->whereIn('id', $subaccountIds)->pluck('legacy_budget_cedula_id')->filter()->all();
        DB::table('budget_cedula_product_service')->insertOrIgnore(
            collect($legacyBudgetCedulaIds)->map(fn (int $budgetCedulaId) => [
                'product_service_id' => $productId,
                'budget_cedula_id' => $budgetCedulaId,
            ])->all()
        );

        $now = now();
        $rules = collect($departmentIds)->map(fn (int $departmentId) => [
            'product_service_id' => $productId,
            'department_id' => $departmentId,
            'subaccount_id' => $departmentId === 3 ? $subaccounts['Mantenimiento General'] : $subaccounts['Limpieza'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('product_department_subaccount')->upsert(
            $rules,
            ['product_service_id', 'department_id'],
            ['subaccount_id', 'updated_at']
        );
    }
};
