<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductServiceDatatableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);
        Role::findOrCreate('superadmin', 'web');
    }

    public function test_catalog_datatable_returns_paginated_product_rows(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $product = ProductService::factory()->create();

        $response = $this->actingAs($user)->getJson(route('products-services.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]));

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->assertJsonFragment(['code' => $product->code]);
    }
}
