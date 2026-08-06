<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\EnsureSupplierIsApproved;
use App\Livewire\Dashboard\DashboardContent;
use App\Models\QuotationSummary;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);

        foreach ([
            'staff',
            'buyer',
            'authorizer',
            'receiver',
            'accounting',
            'department_head',
            'general_director',
            'catalog_admin',
            'superadmin',
        ] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_staff_dashboard_shows_requisition_widgets_and_empty_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Vista operativa')
            ->assertSeeText('Tu resumen operativo se cargara al continuar')
            ->assertDontSeeText('Mis requisiciones recientes');

        Livewire::actingAs($user)
            ->test(DashboardContent::class, ['lazy' => false])
            ->assertSeeText('Mis requisiciones recientes')
            ->assertSeeText('Mis borradores')
            ->assertSeeText('En proceso')
            ->assertSeeText('Cotizadas')
            ->assertSeeText('Nueva requisicion')
            ->assertSeeText('Aun no has creado requisiciones.')
            ->assertDontSeeText('Bandeja financiera');
    }

    public function test_staff_dashboard_counts_quoted_requisition_in_quoted_kpi(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        Requisition::factory()->create([
            'requested_by' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'status' => 'QUOTED',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        Livewire::actingAs($user)
            ->test(DashboardContent::class, ['lazy' => false])
            ->assertSeeText('Cotizadas')
            ->assertSeeText('1')
            ->assertSeeText('Mis requisiciones recientes');
    }

    public function test_buyer_dashboard_shows_operational_purchase_widgets(): void
    {
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');

        $requester = User::factory()->create();
        $requisition = Requisition::factory()->create([
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
            'status' => 'IN_QUOTATION',
        ]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => null,
            'requisition_item_id' => $item->id,
            'status' => 'SENT',
            'response_deadline' => now()->addDays(2),
        ]);
        $supplier = Supplier::factory()->create();
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now()]);

        QuotationSummary::factory()->create([
            'requisition_id' => $requisition->id,
            'rfq_id' => $rfq->id,
            'current_approver_user_id' => $buyer->id,
            'approval_status' => 'pending',
            'total' => 1250,
        ]);

        $this->actingAs($buyer)->get(route('dashboard'))->assertOk();

        Livewire::actingAs($buyer)
            ->test(DashboardContent::class, ['lazy' => false])
            ->assertSeeText('Gestionar RFQs')
            ->assertSeeText('Bandeja operativa de compras')
            ->assertSeeText('RFQs enviadas')
            ->assertSeeText('Por aprobar')
            ->assertSeeText('Revision documental');
    }

    public function test_accounting_dashboard_hides_purchase_widgets_and_shows_finance_widgets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('accounting');

        $supplier = Supplier::factory()->create();
        SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'uuid' => 'UUID-ACCOUNTING-001',
            'xml_path' => 'invoices/test.xml',
            'pdf_path' => 'invoices/test.pdf',
            'issuer_rfc' => $supplier->rfc,
            'receiver_rfc' => 'XAXX010101000',
            'subtotal' => 100,
            'iva_amount' => 16,
            'total' => 116,
            'currency' => 'MXN',
            'uploaded_origin' => SupplierInvoice::ORIGIN_SUPPLIER,
            'status' => SupplierInvoice::STATUS_UPLOADED,
            'uploaded_by_supplier_id' => $supplier->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        Livewire::actingAs($user)
            ->test(DashboardContent::class, ['lazy' => false])
            ->assertSeeText('Bandeja financiera')
            ->assertSeeText('Facturas cargadas')
            ->assertSeeText('Provisiones pendientes')
            ->assertDontSeeText('Mis requisiciones recientes')
            ->assertDontSeeText('Gestionar RFQs');
    }

    public function test_multi_role_dashboard_combines_widgets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');
        $user->assignRole('accounting');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        Livewire::actingAs($user)
            ->test(DashboardContent::class, ['lazy' => false])
            ->assertSeeText('Mis requisiciones recientes')
            ->assertSeeText('Bandeja financiera')
            ->assertSeeText('Nueva requisicion')
            ->assertSeeText('Facturas');
    }

    public function test_superadmin_dashboard_shows_consolidated_view(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        Livewire::actingAs($user)
            ->test(DashboardContent::class, ['lazy' => false])
            ->assertSeeText('Vista consolidada')
            ->assertSeeText('Mis requisiciones recientes')
            ->assertSeeText('Bandeja financiera')
            ->assertSeeText('Usuarios staff');
    }

    public function test_supplier_dashboard_uses_new_board_and_keeps_rfq_flow_visible(): void
    {
        $this->withoutMiddleware(EnsureSupplierIsApproved::class);

        $supplier = Supplier::factory()->create([
            'approval_status' => 'approved',
            'document_status' => 'pending',
        ]);

        $requester = User::factory()->create();
        $requisition = Requisition::factory()->create([
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'status' => 'IN_QUOTATION',
            'receiving_location_id' => ReceivingLocation::factory()->create()->id,
        ]);
        $item = RequisitionItem::factory()->create(['requisition_id' => $requisition->id]);
        $rfq = Rfq::factory()->create([
            'requisition_id' => $requisition->id,
            'quotation_group_id' => null,
            'requisition_item_id' => $item->id,
            'status' => 'SENT',
            'response_deadline' => now()->addDays(5),
        ]);
        $rfq->suppliers()->attach($supplier->id, ['invited_at' => now()]);

        RfqResponse::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $item->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($supplier, 'supplier')->get(route('supplier.dashboard'));

        $response->assertOk()
            ->assertSeeText('Portal de Proveedores')
            ->assertSeeText('Mis RFQs recientes')
            ->assertSeeText('Tu alta aun requiere documentacion')
            ->assertSeeText('Documentacion')
            ->assertSeeText($rfq->folio);
    }
}
