<?php

namespace Tests\Feature;

use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceSatProductCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sat_product_code_is_shown_in_product_forms_and_detail(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $product = ProductService::factory()->create([
            'sat_product_code' => '27112916',
        ]);

        $this->actingAs($user)
            ->get(route('products-services.create'))
            ->assertOk()
            ->assertSee('name="sat_product_code"', false);

        $this->actingAs($user)
            ->get(route('products-services.edit', $product))
            ->assertOk()
            ->assertSee('value="27112916"', false);

        $this->actingAs($user)
            ->get(route('products-services.show', $product))
            ->assertOk()
            ->assertSee('Código SAT')
            ->assertSee('27112916');
    }
}
