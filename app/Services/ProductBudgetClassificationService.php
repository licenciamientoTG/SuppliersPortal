<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\Subaccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductBudgetClassificationService
{
    public function resolveForProduct(ProductService|int $product): array
    {
        $product = $product instanceof ProductService
            ? $product
            : ProductService::query()->findOrFail($product);

        $subaccount = $product->subaccounts()
            ->with('account')
            ->whereNotNull('legacy_budget_cedula_id')
            ->orderBy('subaccounts.id')
            ->first();

        if (! $subaccount) {
            throw new RuntimeException('El producto no tiene subcuenta presupuestal asignada.');
        }

        $account = $subaccount->account;
        $budgetCedula = BudgetCedula::query()->find($subaccount->legacy_budget_cedula_id);

        if (! $account || ! $budgetCedula || ! $account->legacy_expense_category_id) {
            throw new RuntimeException('La subcuenta del producto no tiene equivalencia presupuestal completa.');
        }

        $expenseCategory = ExpenseCategory::query()->find($account->legacy_expense_category_id);

        if (! $expenseCategory || (int) $budgetCedula->expense_category_id !== (int) $expenseCategory->id) {
            throw new RuntimeException('La cuenta y subcuenta del producto no coinciden con la subcuenta legacy.');
        }

        return [
            'account_id' => (int) $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'subaccount_id' => (int) $subaccount->id,
            'subaccount_code' => $subaccount->code,
            'subaccount_name' => $subaccount->name,
            'expense_category_id' => (int) $expenseCategory->id,
            'expense_category_name' => $expenseCategory->name,
            'budget_cedula_id' => (int) $budgetCedula->id,
            'budget_cedula_name' => $budgetCedula->name,
            'is_fixed_asset' => (bool) ($subaccount->is_fixed_asset || $account->is_fixed_asset),
        ];
    }

    public function backfillIncompleteProducts(): array
    {
        $subaccounts = Subaccount::query()
            ->with('account')
            ->where('is_active', true)
            ->whereNotNull('legacy_budget_cedula_id')
            ->whereHas('account', fn ($query) => $query->whereNotNull('legacy_expense_category_id'))
            ->orderBy('id')
            ->get();

        if ($subaccounts->isEmpty()) {
            throw new RuntimeException('No hay subcuentas activas con equivalencia legacy para asignar productos.');
        }

        $stats = [
            'products_seen' => 0,
            'products_updated' => 0,
            'pivots_synced' => 0,
            'account_numbers_filled' => 0,
        ];

        ProductService::query()
            ->with(['subaccounts', 'budgetCedulas'])
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($subaccounts, &$stats) {
                foreach ($products as $product) {
                    $stats['products_seen']++;

                    $needsSubaccount = $product->subaccounts->isEmpty() || $product->budgetCedulas->isEmpty();
                    $needsNumbers = blank($product->account_major)
                        || blank($product->account_sub)
                        || blank($product->account_subsub);

                    if (! $needsSubaccount && ! $needsNumbers) {
                        continue;
                    }

                    $subaccount = $product->subaccounts
                        ->filter(fn (Subaccount $subaccount) => filled($subaccount->legacy_budget_cedula_id))
                        ->sortBy('id')
                        ->first()
                        ?? $this->deterministicSubaccountFor($product, $subaccounts);

                    $result = $this->syncProductClassification($product, $subaccount);
                    $stats['pivots_synced'] += $result['pivots_synced'];
                    $stats['account_numbers_filled'] += $result['account_numbers_filled'];

                    $stats['products_updated']++;
                }
            });

        return $stats;
    }

    public function ensureProductHasBudgetClassification(ProductService $product): void
    {
        $product->loadMissing(['subaccounts.account', 'budgetCedulas']);

        $needsSubaccount = $product->subaccounts->isEmpty() || $product->budgetCedulas->isEmpty();
        $needsNumbers = blank($product->account_major)
            || blank($product->account_sub)
            || blank($product->account_subsub);

        if (! $needsSubaccount && ! $needsNumbers) {
            return;
        }

        $subaccount = $product->subaccounts
            ->filter(fn (Subaccount $subaccount) => filled($subaccount->legacy_budget_cedula_id) && $subaccount->account?->legacy_expense_category_id)
            ->sortBy('id')
            ->first();

        if (! $subaccount) {
            $subaccounts = Subaccount::query()
                ->with('account')
                ->where('is_active', true)
                ->whereNotNull('legacy_budget_cedula_id')
                ->whereHas('account', fn ($query) => $query->whereNotNull('legacy_expense_category_id'))
                ->orderBy('id')
                ->get();

            if ($subaccounts->isEmpty()) {
                throw new RuntimeException('No hay subcuentas activas con equivalencia legacy para asignar productos.');
            }

            $subaccount = $this->deterministicSubaccountFor($product, $subaccounts);
        }

        $this->syncProductClassification($product, $subaccount);
    }

    public function accountNumbersFor(ProductService $product, Account $account, Subaccount $subaccount): array
    {
        $categoryName = mb_strtolower($account->name);

        $major = match (true) {
            str_contains($categoryName, 'ingreso') => 4000,
            str_contains($categoryName, 'costo de venta') => 5000,
            str_contains($categoryName, 'nomina'), str_contains($categoryName, 'social') => 6000,
            str_contains($categoryName, 'mantenimiento') => 6200,
            str_contains($categoryName, 'gasto') => 6100,
            str_contains($categoryName, 'depreci') || str_contains($categoryName, 'amort') => 6800,
            str_contains($categoryName, 'proyecto') || str_contains($categoryName, 'extraordinaria') => 7000,
            default => 6900,
        };

        $legacyCedulaId = (int) ($subaccount->legacy_budget_cedula_id ?: $subaccount->id);
        $hash = abs(crc32($product->code.'|'.$product->id));

        return [
            'account_major' => (string) $major,
            'account_sub' => (string) ($major + ($legacyCedulaId % 900) + 1),
            'account_subsub' => (string) (1000 + ($hash % 9000)),
        ];
    }

    private function deterministicSubaccountFor(ProductService $product, $subaccounts): Subaccount
    {
        $hash = abs(crc32($product->code.'|'.$product->short_name.'|'.$product->id));
        $index = $hash % $subaccounts->count();

        return $subaccounts->values()->get($index);
    }

    private function syncProductClassification(ProductService $product, Subaccount $subaccount): array
    {
        $account = $subaccount->account;

        if (! $account || ! $account->legacy_expense_category_id || ! $subaccount->legacy_budget_cedula_id) {
            throw new RuntimeException('La subcuenta del producto no tiene equivalencia presupuestal completa.');
        }

        $budgetCedulaId = (int) $subaccount->legacy_budget_cedula_id;
        $expenseCategoryId = (int) $account->legacy_expense_category_id;

        DB::table('product_service_subaccount')->updateOrInsert([
            'product_service_id' => $product->id,
            'subaccount_id' => $subaccount->id,
        ]);

        DB::table('budget_cedula_product_service')->updateOrInsert([
            'product_service_id' => $product->id,
            'budget_cedula_id' => $budgetCedulaId,
        ]);

        DB::table('account_product_service')->updateOrInsert([
            'product_service_id' => $product->id,
            'account_id' => $account->id,
        ]);

        DB::table('expense_category_product_service')->updateOrInsert([
            'product_service_id' => $product->id,
            'expense_category_id' => $expenseCategoryId,
        ]);

        $numbersFilled = 0;
        $needsNumbers = blank($product->account_major)
            || blank($product->account_sub)
            || blank($product->account_subsub);

        if ($needsNumbers) {
            $numbers = $this->accountNumbersFor($product, $account, $subaccount);
            $product->forceFill([
                'account_major' => $product->account_major ?: $numbers['account_major'],
                'account_sub' => $product->account_sub ?: $numbers['account_sub'],
                'account_subsub' => $product->account_subsub ?: $numbers['account_subsub'],
            ])->save();

            $numbersFilled = 1;
        }

        return [
            'pivots_synced' => 1,
            'account_numbers_filled' => $numbersFilled,
        ];
    }
}
