<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierExternalFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_external_defaults_to_false_and_casts_to_boolean(): void
    {
        $supplier = Supplier::factory()->create();

        $this->assertIsBool($supplier->fresh()->is_external);
        $this->assertFalse($supplier->fresh()->is_external);
    }

    public function test_external_state_marks_supplier_as_external_and_inactive(): void
    {
        $supplier = Supplier::factory()->external()->create();

        $this->assertTrue($supplier->fresh()->is_external);
        $this->assertFalse($supplier->fresh()->is_active);
    }

    public function test_scope_external_filters_only_external_suppliers(): void
    {
        $regular = Supplier::factory()->create();
        $external = Supplier::factory()->external()->create();

        $this->assertEquals([$external->id], Supplier::external()->pluck('id')->all());
        $this->assertNotContains($regular->id, Supplier::external()->pluck('id')->all());
    }
}
