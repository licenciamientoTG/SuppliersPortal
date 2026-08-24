<?php

namespace Tests\Feature;

use App\Models\SatRetencion;
use App\Models\TaxCode;
use Database\Seeders\SatRetencionSeeder;
use Database\Seeders\TaxCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCodeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_the_one_goal_simple_catalog_without_merging_duplicate_codes(): void
    {
        $this->seed(TaxCodeSeeder::class);

        $this->assertDatabaseCount('tax_codes', 32);
        $this->assertDatabaseHas('tax_codes', [
            'one_goal_id' => 0,
            'name' => 'SIN ASIGNAR',
            'is_selectable' => false,
        ]);
        $this->assertDatabaseHas('tax_codes', [
            'one_goal_id' => 3,
            'name' => 'IVA TASA 0%',
            'is_exempt' => false,
        ]);
        $this->assertDatabaseHas('tax_codes', [
            'one_goal_id' => 4,
            'name' => 'IVA EXENTO',
            'is_exempt' => true,
        ]);
        $this->assertSame(2, TaxCode::query()->where('name', 'IEPS 25%')->count());
        $this->assertSame('-10.0000', TaxCode::query()->where('one_goal_id', 5)->firstOrFail()->rate);
    }

    public function test_it_links_sat_withholdings_to_their_one_goal_tax_code(): void
    {
        $this->seed(TaxCodeSeeder::class);
        $this->seed(SatRetencionSeeder::class);

        $this->assertSame(5, SatRetencion::query()->where('clave', 'ISR-HON')->firstOrFail()->taxCode->one_goal_id);
        $this->assertSame(15, SatRetencion::query()->where('clave', 'IVA-HON')->firstOrFail()->taxCode->one_goal_id);
        $this->assertSame(7, SatRetencion::query()->where('clave', 'IVA-TRA')->firstOrFail()->taxCode->one_goal_id);
        $this->assertNull(SatRetencion::query()->where('clave', 'ISR-INT')->value('tax_code_id'));
    }
}
