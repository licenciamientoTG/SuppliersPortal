<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Mail\RequisitionFeedbackMail;
use App\Models\BudgetCedula;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RequisitionFeedbackFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_send_feedback_and_it_is_stored_with_mail_cc(): void
    {
        Mail::fake();
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        ['requisition' => $requisition, 'buyer' => $buyer, 'requester' => $requester] = $this->createRequisitionFixture();

        $response = $this->actingAs($buyer)->post(route('requisitions.feedback', $requisition), [
            'message' => 'Favor de aclarar la marca requerida y confirmar si el tiempo de entrega puede ajustarse.',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('requisition_feedback', [
            'requisition_id' => $requisition->id,
            'buyer_user_id' => $buyer->id,
            'message' => 'Favor de aclarar la marca requerida y confirmar si el tiempo de entrega puede ajustarse.',
        ]);

        Mail::assertSent(RequisitionFeedbackMail::class, function (RequisitionFeedbackMail $mail) use ($requester, $buyer, $requisition) {
            return $mail->hasTo($requester->email)
                && $mail->hasCc($buyer->email)
                && $mail->requisition->is($requisition);
        });
    }

    public function test_feedback_history_keeps_multiple_messages(): void
    {
        Mail::fake();
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        ['requisition' => $requisition, 'buyer' => $buyer] = $this->createRequisitionFixture();

        $this->actingAs($buyer)->post(route('requisitions.feedback', $requisition), [
            'message' => 'Primera retroalimentacion para ajustar la descripcion tecnica.',
        ], ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($buyer)->post(route('requisitions.feedback', $requisition), [
            'message' => 'Segunda retroalimentacion para confirmar unidad de medida y fecha requerida.',
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(2, DB::table('requisition_feedback')->where('requisition_id', $requisition->id)->count());
        $this->assertSame(
            'Segunda retroalimentacion para confirmar unidad de medida y fecha requerida.',
            Requisition::query()->with('feedbacks')->findOrFail($requisition->id)->feedbacks->first()->message
        );
    }

    public function test_feedback_is_visible_in_requisition_detail_for_requester(): void
    {
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        ['requisition' => $requisition, 'buyer' => $buyer, 'requester' => $requester] = $this->createRequisitionFixture();

        DB::table('requisition_feedback')->insert([
            'requisition_id' => $requisition->id,
            'buyer_user_id' => $buyer->id,
            'message' => 'Comprar solicita definir especificacion tecnica y modelo exacto.',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($requester)
            ->get(route('requisitions.show', $requisition))
            ->assertOk()
            ->assertSee('Retroalimentacion de Compras')
            ->assertSee('Comprar solicita definir especificacion tecnica y modelo exacto.')
            ->assertSee($buyer->name);
    }

    public function test_feedback_badge_and_history_are_visible_for_buyer_views(): void
    {
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        ['requisition' => $requisition, 'buyer' => $buyer] = $this->createRequisitionFixture();

        DB::table('requisition_feedback')->insert([
            'requisition_id' => $requisition->id,
            'buyer_user_id' => $buyer->id,
            'message' => 'Es necesario confirmar la compatibilidad del producto con el equipo actual.',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('requisitions.validate.show', $requisition))
            ->assertOk()
            ->assertSee('Historial de Retroalimentacion de Compras')
            ->assertSee('Es necesario confirmar la compatibilidad del producto con el equipo actual.');

        $datatableResponse = $this->actingAs($buyer)
            ->getJson(route('requisitions.approval_datatable'));

        $datatableResponse->assertOk();
        $this->assertStringContainsString('Retroalimentada', $datatableResponse->getContent());
    }

    private function createRequisitionFixture(): array
    {
        Role::findOrCreate('buyer', 'web');
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $requester = User::factory()->create();
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $company = Company::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'responsible_user_id' => $admin->id,
            'budget_type' => 'ANNUAL',
        ]);
        $receivingLocation = ReceivingLocation::factory()->create(['company_id' => $company->id]);
        $expenseCategory = ExpenseCategory::factory()->create(['created_by' => $admin->id]);
        $budgetCedula = BudgetCedula::factory()->create([
            'expense_category_id' => $expenseCategory->id,
            'created_by' => $admin->id,
        ]);

        $productService = ProductService::factory()->create([
            'technical_description' => 'Producto de prueba con descripcion tecnica suficiente para validaciones.',
            'short_name' => 'Producto QA',
            'unit_of_measure' => 'PIEZA',
            'created_by' => $admin->id,
        ]);

        $requisition = Requisition::factory()->create([
            'company_id' => $company->id,
            'receiving_location_id' => $receivingLocation->id,
            'folio' => 'REQ-2026-001',
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'required_date' => now()->toDateString(),
            'description' => 'Requisicion de prueba para retroalimentacion de compras.',
            'status' => 'PENDING',
        ]);

        RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'product_service_id' => $productService->id,
            'line_number' => 1,
            'item_category' => 'producto',
            'product_code' => $productService->code,
            'description' => 'Producto requerido para pruebas de retroalimentacion',
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => $budgetCedula->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 2,
            'unit' => 'PZA',
            'notes' => 'Notas de prueba',
        ]);

        return [
            'buyer' => $buyer,
            'requester' => $requester,
            'requisition' => $requisition->fresh(),
        ];
    }
}
