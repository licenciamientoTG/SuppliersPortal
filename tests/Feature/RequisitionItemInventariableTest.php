<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequisitionItemInventariableTest extends TestCase
{
    use RefreshDatabase;

    public function test_requisition_items_have_non_inventory_default_field(): void
    {
        $this->assertTrue(Schema::hasColumn('requisition_items', 'es_inventariable'));
    }
}
