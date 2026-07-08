<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductServiceSubcategoryColumnRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_subcategory_column_no_longer_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('products_services', 'subcategory'));
    }
}
