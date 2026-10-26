<?php

namespace Tests\Unit;

use App\Models\CfdiRejectionCode;
use App\Models\CfdiValidationLog;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\Fiscal\CfdiValidationLogReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CfdiValidationLogReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('accounting', 'web');
        Role::findOrCreate('superadmin', 'web');
    }

    public function test_full_scope_user_sees_only_logs_of_their_assigned_companies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $this->createLog(['company_id' => $companyA->id, 'uuid' => 'A']);
        $this->createLog(['company_id' => $companyB->id, 'uuid' => 'B']);

        $user = User::factory()->create();
        $user->assignRole('accounting');
        $user->companies()->attach($companyA->id);

        $result = (new CfdiValidationLogReport)->build($user, []);

        $this->assertCount(1, $result['rows']);
        $this->assertSame($companyA->id, $result['rows']->first()['company_id']);
    }

    public function test_user_without_role_and_without_companies_sees_nothing(): void
    {
        $company = Company::factory()->create();
        $this->createLog(['company_id' => $company->id, 'uuid' => 'A']);

        $user = User::factory()->create();

        $result = (new CfdiValidationLogReport)->build($user, []);

        $this->assertCount(0, $result['rows']);
    }

    public function test_attempt_no_is_computed_per_uuid_ordered_by_attempted_at(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->createLog(['company_id' => $company->id, 'uuid' => 'SAME', 'attempted_at' => now()->subMinutes(10)]);
        $this->createLog(['company_id' => $company->id, 'uuid' => 'SAME', 'attempted_at' => now()->subMinutes(5)]);
        $this->createLog(['company_id' => $company->id, 'uuid' => 'SAME', 'attempted_at' => now()]);

        $result = (new CfdiValidationLogReport)->build($user, []);

        $attemptNumbers = $result['rows']->sortBy('attempted_at')->pluck('attempt_no')->values()->all();
        $this->assertSame([1, 2, 3], $attemptNumbers);
    }

    public function test_filters_by_result_and_rejection_code(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->createLog(['company_id' => $company->id, 'uuid' => 'A', 'result' => CfdiValidationLog::RESULT_ACCEPTED, 'rejection_code' => null]);
        $this->createLog(['company_id' => $company->id, 'uuid' => 'B', 'result' => CfdiValidationLog::RESULT_REJECTED, 'rejection_code' => CfdiRejectionCode::UUID_DUPLICADO]);
        $this->createLog(['company_id' => $company->id, 'uuid' => 'C', 'result' => CfdiValidationLog::RESULT_REJECTED, 'rejection_code' => CfdiRejectionCode::EFOS_69B]);

        $result = (new CfdiValidationLogReport)->build($user, [
            'result' => CfdiValidationLog::RESULT_REJECTED,
            'rejection_code' => [CfdiRejectionCode::EFOS_69B],
        ]);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('C', $result['rows']->first()['uuid']);
    }

    public function test_supplier_view_only_shows_its_own_attempts(): void
    {
        $company = Company::factory()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $this->createLog(['company_id' => $company->id, 'uuid' => 'A', 'supplier_id' => $supplierA->id]);
        $this->createLog(['company_id' => $company->id, 'uuid' => 'B', 'supplier_id' => $supplierB->id]);

        $result = (new CfdiValidationLogReport)->buildForSupplier($supplierA, []);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('A', $result['rows']->first()['uuid']);
    }

    public function test_filtering_does_not_change_the_true_historical_attempt_number(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->createLog(['company_id' => $company->id, 'uuid' => 'X', 'attempted_at' => now()->subMinutes(10), 'result' => CfdiValidationLog::RESULT_ACCEPTED]);
        $this->createLog(['company_id' => $company->id, 'uuid' => 'X', 'attempted_at' => now()->subMinutes(5), 'result' => CfdiValidationLog::RESULT_REJECTED]);
        $this->createLog(['company_id' => $company->id, 'uuid' => 'X', 'attempted_at' => now(), 'result' => CfdiValidationLog::RESULT_REJECTED]);

        $result = (new CfdiValidationLogReport)->build($user, ['result' => CfdiValidationLog::RESULT_REJECTED]);

        $attemptNumbers = $result['rows']->sortBy('attempted_at')->pluck('attempt_no')->values()->all();
        $this->assertSame([2, 3], $attemptNumbers);
    }

    public function test_kpis_report_first_attempt_rejection_rate_and_top_code(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        // UUID 1: rechazado en el primer intento, luego aceptado en el segundo.
        $this->createLog(['company_id' => $company->id, 'uuid' => '1', 'attempted_at' => now()->subMinutes(10), 'result' => CfdiValidationLog::RESULT_REJECTED, 'rejection_code' => CfdiRejectionCode::UUID_DUPLICADO]);
        $this->createLog(['company_id' => $company->id, 'uuid' => '1', 'attempted_at' => now()->subMinutes(5), 'result' => CfdiValidationLog::RESULT_ACCEPTED, 'rejection_code' => null]);
        // UUID 2: aceptado en el primer intento.
        $this->createLog(['company_id' => $company->id, 'uuid' => '2', 'attempted_at' => now(), 'result' => CfdiValidationLog::RESULT_ACCEPTED, 'rejection_code' => null]);

        $result = (new CfdiValidationLogReport)->build($user, []);

        $this->assertSame(50.0, $result['kpis']['first_attempt_rejection_rate']);
        $this->assertSame(CfdiRejectionCode::UUID_DUPLICADO, $result['kpis']['top_rejection_code']);
    }

    private function createLog(array $overrides): CfdiValidationLog
    {
        return CfdiValidationLog::create(array_merge([
            'attempted_at' => now(),
            'uuid' => 'X',
            'issuer_rfc' => 'AAA010101AAA',
            'receiver_rfc' => 'TGA010101AAA',
            'total' => 100.00,
            'currency' => 'MXN',
            'result' => CfdiValidationLog::RESULT_ACCEPTED,
            'channel' => CfdiValidationLog::CHANNEL_PORTAL,
        ], $overrides));
    }
}
