<?php

namespace Tests\Feature;

use App\Models\ProductService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceFormSubcategoryFieldsRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_does_not_show_subcategory_or_category_display_fields(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $response = $this->actingAs($user)->get(route('products-services.create'));

        $response->assertOk();
        $response->assertDontSee('name="subcategory"', false);
        $response->assertDontSee('id="category_display"', false);
    }

    public function test_edit_form_does_not_show_subcategory_or_category_display_fields(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('catalog_admin');

        $product = ProductService::factory()->create();

        $response = $this->actingAs($user)->get(route('products-services.edit', $product));

        $response->assertOk();
        $response->assertDontSee('name="subcategory"', false);
        $response->assertDontSee('id="category_display"', false);
    }
}
