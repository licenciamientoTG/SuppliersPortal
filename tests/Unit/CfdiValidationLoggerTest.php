<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CfdiValidationLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CfdiValidationLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_persists_a_row_with_the_given_attributes(): void
    {
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create();

        $log = (new CfdiValidationLogger)->log([
            'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
            'issuer_rfc' => 'AAA010101AAA',
            'receiver_rfc' => 'TGA010101AAA',
            'total' => 116.00,
            'currency' => 'MXN',
            'result' => 'ACEPTADO',
            'channel' => 'PORTAL_PROVEEDOR',
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('cfdi_validation_logs', [
            'id' => $log->id,
            'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
            'result' => 'ACEPTADO',
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
        ]);
        $this->assertNotNull($log->attempted_at);
    }

    public function test_resolve_company_id_matches_by_uppercased_receiver_rfc(): void
    {
        $company = Company::factory()->create(['rfc' => 'tga010101aaa']);

        $companyId = (new CfdiValidationLogger)->resolveCompanyId('TGA010101AAA');

        $this->assertSame($company->id, $companyId);
    }

    public function test_resolve_company_id_returns_null_when_no_match(): void
    {
        $companyId = (new CfdiValidationLogger)->resolveCompanyId('ZZZ010101ZZZ');

        $this->assertNull($companyId);
    }
}
