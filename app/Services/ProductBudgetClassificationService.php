<?php

namespace App\Services;

use App\Models\BudgetCedula;
use App\Models\BudgetProfile;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\Subaccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductBudgetClassificationService
{
    public function resolveForProduct(ProductService|int $product, ?int $departmentId): array
    {
        $product = $product instanceof ProductService
            ? $product
            : ProductService::query()->findOrFail($product);

        if (! $departmentId) {
            throw new RuntimeException('El solicitante debe tener un departamento asignado.');
        }

        $subaccounts = $product->subaccounts()
            ->with('account')
            ->whereNotNull('legacy_budget_cedula_id')
            ->orderBy('subaccounts.id')
            ->get();

        if ($subaccounts->isEmpty()) {
            throw new RuntimeException('El producto no tiene subcuenta presupuestal asignada.');
        }

        if ($subaccounts->count() === 1) {
            $subaccount = $subaccounts->sole();
        } else {
            $subaccount = $product->departmentSubaccountMappings()
                ->where('department_id', $departmentId)
                ->whereIn('subaccount_id', $subaccounts->pluck('id'))
                ->with('subaccount.account')
                ->first()?->subaccount;

            if (! $subaccount) {
                throw new RuntimeException('Este producto no tiene una subcuenta configurada para el departamento del solicitante.');
            }
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

    public function isAvailableForDepartment(ProductService|int $product, ?int $departmentId): bool
    {
        try {
            $this->resolveForProduct($product, $departmentId);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Completa las relaciones presupuestales de productos cuya subcuenta ya fue
     * capturada por una persona. Los productos sin subcuenta se omiten: la
     * clasificacion contable nunca se asigna automaticamente.
     */
    public function backfillIncompleteProducts(): array
    {
        $stats = [
            'products_seen' => 0,
            'products_updated' => 0,
            'products_skipped_without_subaccount' => 0,
        ];

        ProductService::query()
            ->with(['subaccounts.account', 'budgetCedulas'])
            ->orderBy('id')
            ->chunkById(100, function ($products) use (&$stats) {
                foreach ($products as $product) {
                    $stats['products_seen']++;

                    if (! $this->needsRelationSync($product)) {
                        continue;
                    }

                    $subaccount = $this->capturedSubaccountFor($product);

                    if (! $subaccount) {
                        $stats['products_skipped_without_subaccount']++;

                        continue;
                    }

                    $this->syncProductClassification($product, $subaccount);
                    $stats['products_updated']++;
                }
            });

        return $stats;
    }

    /**
     * Sincroniza las relaciones presupuestales a partir de la subcuenta capturada.
     * Si el producto no tiene subcuenta, queda sin clasificar y requiere captura
     * del administrador del catalogo antes de aprobarse o reactivarse.
     */
    public function ensureProductHasBudgetClassification(ProductService $product): void
    {
        $product->loadMissing(['subaccounts.account', 'budgetCedulas']);

        if (! $this->needsRelationSync($product)) {
            return;
        }

        $subaccount = $this->capturedSubaccountFor($product);

        if (! $subaccount) {
            return;
        }

        $this->syncProductClassification($product, $subaccount);
    }

    private function needsRelationSync(ProductService $product): bool
    {
        return $product->subaccounts->isEmpty() || $product->budgetCedulas->isEmpty();
    }

    private function capturedSubaccountFor(ProductService $product): ?Subaccount
    {
        return $product->subaccounts
            ->filter(fn (Subaccount $subaccount) => filled($subaccount->legacy_budget_cedula_id) && $subaccount->account?->legacy_expense_category_id)
            ->sortBy('id')
            ->first();
    }

    private function syncProductClassification(ProductService $product, Subaccount $subaccount): void
    {
        $account = $subaccount->account;

        if (! $account || ! $account->legacy_expense_category_id || ! $subaccount->legacy_budget_cedula_id) {
            throw new RuntimeException('La subcuenta del producto no tiene equivalencia presupuestal completa.');
        }

        DB::table('product_service_subaccount')->updateOrInsert([
            'product_service_id' => $product->id,
            'subaccount_id' => $subaccount->id,
        ]);

        DB::table('budget_cedula_product_service')->updateOrInsert([
            'product_service_id' => $product->id,
            'budget_cedula_id' => (int) $subaccount->legacy_budget_cedula_id,
        ]);

        DB::table('account_product_service')->updateOrInsert([
            'product_service_id' => $product->id,
            'account_id' => $account->id,
        ]);

        DB::table('expense_category_product_service')->updateOrInsert([
            'product_service_id' => $product->id,
            'expense_category_id' => (int) $account->legacy_expense_category_id,
        ]);
    }

    /**
     * Devuelve los departamentos activos elegibles para cada cédula presupuestal,
     * considerando solo aquellos departamentos que tienen al menos un perfil presupuestal
     * activo que incluye la subcuenta asociada a la cédula.
     *
     * @param  iterable<int>|null  $cedulaIds
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\Department>>
     */
    public function eligibleDepartmentsByCedula(?iterable $cedulaIds = null): Collection
    {
        $profilesQuery = BudgetProfile::query()
            ->active()
            ->with([
                'subaccounts' => function ($query) use ($cedulaIds) {
                    $query->active()
                        ->whereNotNull('legacy_budget_cedula_id')
                        ->when($cedulaIds !== null, function ($q) use ($cedulaIds) {
                            $q->whereIn('legacy_budget_cedula_id', collect($cedulaIds)->all());
                        });
                },
                'department' => fn ($query) => $query->active(),
                'departments' => fn ($query) => $query->active(),
            ]);

        $profiles = $profilesQuery->get();

        $departmentsByCedula = collect();

        foreach ($profiles as $profile) {
            $departments = collect();

            if ($profile->department && $profile->department->is_active) {
                $departments->push($profile->department);
            }

            if ($profile->relationLoaded('departments')) {
                foreach ($profile->departments as $dept) {
                    if ($dept->is_active) {
                        $departments->push($dept);
                    }
                }
            }

            if ($departments->isEmpty()) {
                continue;
            }

            foreach ($profile->subaccounts as $subaccount) {
                $cedulaId = (int) $subaccount->legacy_budget_cedula_id;

                if (! $departmentsByCedula->has($cedulaId)) {
                    $departmentsByCedula->put($cedulaId, collect());
                }

                foreach ($departments as $dept) {
                    $departmentsByCedula->get($cedulaId)->push($dept);
                }
            }
        }

        return $departmentsByCedula->map(function ($depts) {
            return $depts->unique('id')->sortBy('name')->values();
        });
    }
}
