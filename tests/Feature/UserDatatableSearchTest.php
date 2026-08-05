<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDatatableSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_a_user_when_intermediate_names_are_omitted(): void
    {
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        $administrator = User::factory()->create();
        $matchingUser = User::factory()->create([
            'name' => 'Usuario legado',
            'first_name' => 'Juan Carlos',
            'last_name' => 'Perez Lopez',
        ]);
        User::factory()->create(['name' => 'Juan Gomez']);

        $response = $this->actingAs($administrator)
            ->getJson(route('users.datatable', [
                'start' => 0,
                'length' => 50,
                'search' => ['value' => 'Juan Perez'],
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingUser->id);
    }
}
