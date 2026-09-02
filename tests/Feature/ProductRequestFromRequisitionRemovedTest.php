<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * PS-01: el alta de productos desde la requisición fue eliminada por completo,
 * junto con la cadena de reactivación automática que dependía de ella.
 * Estas aserciones evitan que la funcionalidad se reintroduzca sin revisarla.
 */
class ProductRequestFromRequisitionRemovedTest extends TestCase
{
    public function test_store_from_requisition_route_is_not_registered(): void
    {
        $this->assertFalse(
            Route::has('products-services.store-from-requisition'),
            'La ruta de alta de producto desde requisición debe permanecer eliminada.'
        );
    }

    public function test_request_product_modal_view_does_not_exist(): void
    {
        $this->assertFalse(
            View::exists('requisitions._request_product_modal'),
            'El modal de solicitud de producto debe permanecer eliminado.'
        );
    }

    public function test_removed_class_files_are_absent(): void
    {
        $removed = [
            'app/Events/ProductServiceApproved.php',
            'app/Listeners/ReactivatePausedRequisitions.php',
            'app/Notifications/NewProductRequestedNotification.php',
            'app/Notifications/RequisitionReactivatedNotification.php',
        ];

        foreach ($removed as $relativePath) {
            $this->assertFileDoesNotExist(
                base_path($relativePath),
                "{$relativePath} pertenece al alta de producto desde requisición y debe permanecer eliminado."
            );
        }
    }

    public function test_event_service_provider_does_not_listen_for_product_approval(): void
    {
        $source = file_get_contents(base_path('app/Providers/EventServiceProvider.php'));

        $this->assertStringNotContainsString('ProductServiceApproved', $source);
        $this->assertStringNotContainsString('ReactivatePausedRequisitions', $source);
    }
}
