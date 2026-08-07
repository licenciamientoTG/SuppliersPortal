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
        $products = ProductService::factory()->count(51)->create();

        $response = $this->actingAs($user)->getJson(route('products-services.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]));

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->assertJsonPath('recordsTotal', 51)
            ->assertJsonCount(50, 'data')
            ->assertJsonFragment(['code' => $products->first()->code]);
    }

    public function test_catalog_datatable_searches_the_short_name_displayed_as_description(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $product = ProductService::factory()->create([
            'technical_description' => 'Descripción técnica distinta',
            'short_name' => 'Nombre visible buscable',
        ]);

        $response = $this->actingAs($user)->getJson(route('products-services.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'search' => ['value' => 'visible buscable', 'regex' => 'false'],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'code', 'name' => 'code', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'product_type_badge', 'name' => 'product_type', 'searchable' => 'true', 'orderable' => 'false'],
                ['data' => 'technical_description', 'name' => 'technical_description', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'unit_of_measure', 'name' => 'unit_of_measure', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'estimated_price', 'name' => 'estimated_price', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'status', 'name' => 'status', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'actions', 'name' => 'actions', 'searchable' => 'false', 'orderable' => 'false'],
            ],
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonFragment(['code' => $product->code]);
    }
}
