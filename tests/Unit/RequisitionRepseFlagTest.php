<?php

namespace Tests\Unit;

use App\Models\ExpenseCategory;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionRepseFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_sets_is_repse_true_when_a_service_item_exists(): void
    {
        $services = ExpenseCategory::factory()->create(['code' => 'SER']);
        $requisition = Requisition::factory()->create();
        RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'expense_category_id' => $services->id,
        ]);

        $requisition->refreshRepseFlag();

        $this->assertTrue($requisition->fresh()->is_repse);
    }

    public function test_refresh_sets_is_repse_false_when_no_service_item_exists(): void
    {
        $goods = ExpenseCategory::factory()->create(['code' => 'BIEN']);
        $requisition = Requisition::factory()->create();
        RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'expense_category_id' => $goods->id,
        ]);

        $requisition->refreshRepseFlag();

        $this->assertFalse($requisition->fresh()->is_repse);
    }
}
