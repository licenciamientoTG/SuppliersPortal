<?php

namespace Tests\Feature;

use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExchangeRate;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DirectPurchaseOrderFormRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Renderiza el partial del formulario y devuelve [html, scripts].
     */
    private function renderForm(array $data = [], array $errors = [], array $oldInput = []): array
    {
        $this->actingAs(User::factory()->create());

        if ($oldInput) {
            $session = $this->app['session.store'];
            $session->flashInput($oldInput);
            request()->setLaravelSession($session);
        }

        $errorBag = (new ViewErrorBag)->put('default', new MessageBag($errors));

        // Las pilas @push se vacían al terminar el render: se leen con @stack en la misma pasada.
        $output = Blade::render(
            "@include('direct-purchase-orders.partials._form')<!--SCRIPTS-->@stack('scripts')",
            array_merge([
                'suppliers' => collect(),
                'companies' => collect(),
                'costCenters' => collect(),
                'receivingLocations' => collect(),
                'errors' => $errorBag,
            ], $data),
            deleteCachedView: true,
        );

        return explode('<!--SCRIPTS-->', $output, 2);
    }

    public function test_edit_mode_preselects_saved_product_per_item(): void
    {
        $product = ProductService::factory()->create();
        $ocd = DirectPurchaseOrder::factory()->create();
        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $ocd->id,
            'product_service_id' => $product->id,
        ]);

        [$html] = $this->renderForm(['directPurchaseOrder' => $ocd->load('items', 'documents')]);

        $this->assertStringContainsString('data-selected-product="'.$product->id.'"', $html);
    }

    public function test_form_markup_is_accessible_and_ordered(): void
    {
        [$html] = $this->renderForm();

        // Empresa (que define centros de costo) va antes que Proveedor.
        $this->assertLessThan(strpos($html, 'id="supplier_id"'), strpos($html, 'id="company_id"'));

        foreach (['company_id', 'receiving_location_id', 'supplier_id', 'currency', 'justification', 'quotation_file', 'support_documents'] as $field) {
            $this->assertStringContainsString('for="'.$field.'"', $html);
        }

        $this->assertStringContainsString('aria-label="Centro de costo"', $html);
        $this->assertStringContainsString('aria-label="Eliminar partida"', $html);
        $this->assertStringContainsString('aria-label="Duplicar partida"', $html);
        // Etiquetas para el diseño de tarjetas en pantallas angostas.
        foreach (['Centro de costo', 'Producto y nota', 'Cantidad', 'Precio unit.', 'IVA', 'Subtotal', 'Total'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html);
        }
        // El IVA de la partida ya no se muestra entre paréntesis (se leía como negativo).
        $this->assertStringNotContainsString('value="($', $html);
    }

    public function test_server_errors_are_summarized_once_inside_the_form(): void
    {
        [$html] = $this->renderForm(errors: [
            'error' => ['Error al crear la OCD: Presupuesto insuficiente.'],
            'supplier_id' => ['Debe seleccionar un proveedor.'],
        ]);

        $this->assertStringContainsString('No se pudo guardar la OCD.', $html);
        // El error sin campo propio va en la alerta; el del proveedor solo junto a su campo.
        $this->assertSame(1, substr_count($html, 'Presupuesto insuficiente.'));
        $this->assertSame(1, substr_count($html, 'Debe seleccionar un proveedor.'));
        $this->assertStringContainsString('Revisa el campo marcado', $html);
    }

    public function test_mobile_bar_and_sentence_case_labels_are_rendered(): void
    {
        [$html] = $this->renderForm();

        $this->assertStringContainsString('id="ocd-mobile-bar"', $html);
        $this->assertStringContainsString('id="mobile-submit-btn"', $html);
        $this->assertStringContainsString('Días de entrega', $html);
        $this->assertStringContainsString('Condiciones de pago', $html);
        $this->assertStringNotContainsString('Días Entrega', $html);
    }

    public function test_every_select_inside_a_select2_input_group_is_initialized(): void
    {
        [$html, $scripts] = $this->renderForm();

        // El CSS oculta el <select> nativo dentro de .input-group-select2; si no se
        // inicializa con Select2, el campo queda invisible (pasó con Moneda).
        preg_match_all('/input-group-select2">.*?<select[^>]*\bid="([^"]+)"/s', $html, $matches);

        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $id) {
            $this->assertMatchesRegularExpression(
                '/\$\(\'[^\']*#'.preg_quote($id, '/').'\b[^\']*\'\)\.select2\(/',
                $scripts,
                "El select #{$id} está oculto por .input-group-select2 pero no se inicializa con Select2."
            );
        }
    }

    public function test_flash_partial_hides_validation_list_only_when_the_view_asks(): void
    {
        $errorBag = (new ViewErrorBag)->put('default', new MessageBag(['supplier_id' => ['Mensaje de prueba']]));

        $withList = Blade::render("@include('layouts.partials.flash')@stack('scripts')", ['errors' => $errorBag], deleteCachedView: true);
        $hidden = Blade::render("@section('hide_global_errors', '1')@include('layouts.partials.flash')@stack('scripts')", ['errors' => $errorBag], deleteCachedView: true);

        $this->assertStringContainsString('Mensaje de prueba', $withList);
        $this->assertStringNotContainsString('Mensaje de prueba', $hidden);
    }

    public function test_validation_errors_are_shown_on_the_failing_item_row(): void
    {
        $oldItems = [
            0 => ['product_service_id' => '11', 'cost_center_id' => '', 'description' => 'A', 'quantity' => '1', 'unit_price' => '10', 'iva_rate' => '16'],
            2 => ['product_service_id' => '22', 'cost_center_id' => '', 'description' => 'B', 'quantity' => '0', 'unit_price' => '10', 'iva_rate' => '16'],
        ];

        [$html, $scripts] = $this->renderForm(
            errors: ['items.2.quantity' => ['La cantidad debe ser mayor a 0.']],
            oldInput: ['items' => $oldItems],
        );

        $this->assertStringContainsString('Hay 1 partida con errores', $html);
        $this->assertStringContainsString('La cantidad debe ser mayor a 0.', $html);
        $this->assertStringContainsString('data-selected-product="22"', $html);
        $this->assertMatchesRegularExpression('/name="items\[2\]\[quantity\]"\s+class="[^"]*is-invalid/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="items\[0\]\[quantity\]"\s+class="[^"]*is-invalid/', $html);

        // La siguiente partida nueva no debe reutilizar el índice 2.
        $this->assertStringContainsString('let itemIndex = 3;', $scripts);
    }

    public function test_total_limit_error_and_exchange_rates_reach_the_summary(): void
    {
        ExchangeRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'MXN',
            'rate' => 18.5,
            'fetched_at' => now(),
        ]);

        [$html, $scripts] = $this->renderForm(errors: [
            'total' => ['El total de la OCD ($370,000.00 MXN) excede el límite máximo de $250,000.00 MXN.'],
        ]);

        $this->assertStringContainsString('excede el límite máximo de $250,000.00 MXN', $html);
        $this->assertStringContainsString('"USD":18.5', $scripts);
        $this->assertStringContainsString('"EUR":null', $scripts);
    }
}
