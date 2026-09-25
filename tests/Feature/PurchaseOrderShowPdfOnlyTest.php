<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderShowPdfOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);
    }

    private function buyer(): User
    {
        return User::factory()->create()->assignRole(Role::findOrCreate('buyer', 'web'));
    }

    public function test_issued_direct_order_only_offers_the_branded_pdf_in_a_new_tab(): void
    {
        $ocd = DirectPurchaseOrder::factory()->create(['status' => 'ISSUED']);
        DirectPurchaseOrderItem::factory()->create(['direct_purchase_order_id' => $ocd->id]);

        $this->actingAs($this->buyer())
            ->get(route('direct-purchase-orders.show', $ocd))
            ->assertOk()
            ->assertSee('href="'.route('direct-purchase-orders.pdf.view', $ocd).'" target="_blank"', false)
            ->assertDontSee('window.print()', false)
            ->assertDontSee('ti-file-type-doc', false);
    }

    public function test_issued_purchase_order_only_offers_the_branded_pdf_in_a_new_tab(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => 'ISSUED']);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $purchaseOrder->id]);

        $this->actingAs($this->buyer())
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('href="'.route('purchase-orders.pdf.view', $purchaseOrder).'" target="_blank"', false)
            ->assertDontSee('window.print()', false);
    }

    public function test_word_export_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('direct-purchase-orders.word'));
        $this->assertFileDoesNotExist(resource_path('views/purchase-orders/direct-word.blade.php'));
    }
}
