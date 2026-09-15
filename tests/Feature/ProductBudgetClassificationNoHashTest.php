<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\Subaccount;
use App\Models\User;
use App\Services\ProductBudgetClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBudgetClassificationNoHashTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_without_captured_subaccount_is_not_assigned_one_automatically(): void
    {
        $this->makeActiveClassification();
        $product = $this->makeProductWithoutAccountNumbers();

        app(ProductBudgetClassificationService::class)->ensureProductHasBudgetClassification($product);

        $this->assertDatabaseMissing('product_service_subaccount', ['product_service_id' => $product->id]);
        $this->assertDatabaseMissing('budget_cedula_product_service', ['product_service_id' => $product->id]);
        $this->assertAccountNumbersNotFabricated($product);
    }

    public function test_product_with_captured_subaccount_syncs_relations_without_fabricating_account_numbers(): void
    {
        $classification = $this->makeActiveClassification();
        $product = $this->makeProductWithoutAccountNumbers();
        $product->subaccounts()->sync([$classification['subaccount']->id]);

        app(ProductBudgetClassificationService::class)->ensureProductHasBudgetClassification($product);

        $this->assertDatabaseHas('budget_cedula_product_service', [
            'product_service_id' => $product->id,
            'budget_cedula_id' => $classification['cedula']->id,
        ]);
        $this->assertDatabaseHas('account_product_service', [
            'product_service_id' => $product->id,
            'account_id' => $classification['account']->id,
        ]);
        $this->assertDatabaseHas('expense_category_product_service', [
            'product_service_id' => $product->id,
            'expense_category_id' => $classification['category']->id,
        ]);
        $this->assertAccountNumbersNotFabricated($product);
    }

    public function test_backfill_skips_products_without_captured_subaccount(): void
    {
        $this->makeActiveClassification();
        $product = $this->makeProductWithoutAccountNumbers();

        $stats = app(ProductBudgetClassificationService::class)->backfillIncompleteProducts();

        $this->assertSame(1, $stats['products_skipped_without_subaccount']);
        $this->assertSame(0, $stats['products_updated']);
        $this->assertDatabaseMissing('product_service_subaccount', ['product_service_id' => $product->id]);
        $this->assertAccountNumbersNotFabricated($product);
    }

    public function test_backfill_syncs_products_with_captured_subaccount_without_fabricating_account_numbers(): void
    {
        $classification = $this->makeActiveClassification();
        $product = $this->makeProductWithoutAccountNumbers();
        $product->subaccounts()->sync([$classification['subaccount']->id]);

        $stats = app(ProductBudgetClassificationService::class)->backfillIncompleteProducts();

        $this->assertSame(1, $stats['products_updated']);
        $this->assertSame(0, $stats['products_skipped_without_subaccount']);
        $this->assertDatabaseHas('budget_cedula_product_service', [
            'product_service_id' => $product->id,
            'budget_cedula_id' => $classification['cedula']->id,
        ]);
        $this->assertAccountNumbersNotFabricated($product);
    }

    private function assertAccountNumbersNotFabricated(ProductService $product): void
    {
        $product->refresh();

        $this->assertNull($product->account_major);
        $this->assertNull($product->account_sub);
        $this->assertNull($product->account_subsub);
    }

    private function makeProductWithoutAccountNumbers(): ProductService
    {
        return ProductService::factory()->create([
            'account_major' => null,
            'account_sub' => null,
            'account_subsub' => null,
        ]);
    }

    private function makeActiveClassification(): array
    {
        $user = User::factory()->create();
        $category = ExpenseCategory::factory()->create(['code' => 'MNT', 'created_by' => $user->id]);
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $category->id, 'created_by' => $user->id]);
        $account = Account::factory()->create(['legacy_expense_category_id' => $category->id, 'created_by' => $user->id]);
        $subaccount = Subaccount::factory()->create([
            'account_id' => $account->id,
            'legacy_budget_cedula_id' => $cedula->id,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return compact('category', 'cedula', 'account', 'subaccount');
    }
}
