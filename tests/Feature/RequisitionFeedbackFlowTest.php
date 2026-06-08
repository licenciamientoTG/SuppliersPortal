<?php

namespace Tests\Feature;

use App\Mail\RequisitionFeedbackMail;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RequisitionFeedbackFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_send_feedback_and_it_is_stored_with_mail_cc(): void
    {
        Mail::fake();
        $this->withoutMiddleware();

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
        $this->withoutMiddleware();

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
        $this->withoutMiddleware();

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
        $this->withoutMiddleware();

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
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $requester = User::factory()->create(['email' => 'requester@example.com']);
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'OPERACION',
            'description' => 'Categoria de prueba',
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'code' => 'TGS',
            'name' => 'TotalGas QA',
            'legal_name' => 'TotalGas QA SA de CV',
            'rfc' => 'TGS010101AAA',
            'locale' => 'es_MX',
            'timezone' => 'America/Mexico_City',
            'currency_code' => 'MXN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $costCenterId = DB::table('cost_centers')->insertGetId([
            'code' => 'CC-001',
            'name' => 'Centro de costo QA',
            'description' => 'Centro de costo para pruebas',
            'purchase_type' => 'Gasto Operativo',
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'responsible_user_id' => $admin->id,
            'budget_type' => 'ANNUAL',
            'status' => 'ACTIVO',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receivingLocationId = DB::table('receiving_locations')->insertGetId([
            'code' => 'REC-001',
            'name' => 'Recepcion QA',
            'type' => 'corporate',
            'is_active' => true,
            'portal_blocked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expenseCategoryId = DB::table('expense_categories')->insertGetId([
            'code' => 'EXP-001',
            'name' => 'Gasto Operativo',
            'description' => 'Categoria de gasto QA',
            'status' => 'ACTIVO',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productServiceId = DB::table('products_services')->insertGetId([
            'code' => 'PROD-000001',
            'technical_description' => 'Producto de prueba con descripcion tecnica suficiente para validaciones.',
            'short_name' => 'Producto QA',
            'product_type' => 'PRODUCTO',
            'category_id' => $categoryId,
            'cost_center_id' => $costCenterId,
            'company_id' => $companyId,
            'unit_of_measure' => 'PIEZA',
            'estimated_price' => 100,
            'currency_code' => 'MXN',
            'status' => 'ACTIVE',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requisitionId = DB::table('requisitions')->insertGetId([
            'company_id' => $companyId,
            'receiving_location_id' => $receivingLocationId,
            'department_id' => null,
            'folio' => 'REQ-2026-001',
            'requested_by' => $requester->id,
            'required_date' => now()->toDateString(),
            'description' => 'Requisicion de prueba para retroalimentacion de compras.',
            'status' => 'PENDING',
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('requisition_items')->insert([
            'requisition_id' => $requisitionId,
            'product_service_id' => $productServiceId,
            'line_number' => 1,
            'item_category' => 'producto',
            'product_code' => 'PROD-000001',
            'description' => 'Producto requerido para pruebas de retroalimentacion',
            'expense_category_id' => $expenseCategoryId,
            'cost_center_id' => $costCenterId,
            'quantity' => 2,
            'unit' => 'PZA',
            'notes' => 'Notas de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'buyer' => $buyer,
            'requester' => $requester,
            'requisition' => Requisition::query()->findOrFail($requisitionId),
        ];
    }
}
