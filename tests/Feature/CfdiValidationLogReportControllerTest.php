<?php

namespace Tests\Feature;

use App\Models\CfdiValidationLog;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierDocumentRequirement;
use App\Models\SupplierDocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CfdiValidationLogReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('reportes.cfdi_validation_log.ver', 'web');
        Permission::findOrCreate('reportes.cfdi_validation_log.exportar', 'web');
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('cfdi-validation-log-reports.index'))->assertForbidden();
        $this->actingAs($user)->getJson(route('cfdi-validation-log-reports.data'))->assertForbidden();
    }

    public function test_user_with_permission_only_sees_logs_of_their_assigned_company(): void
    {
        $role = Role::findOrCreate('accounting', 'web');
        $role->givePermissionTo(['reportes.cfdi_validation_log.ver', 'reportes.cfdi_validation_log.exportar']);

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $this->createLog(['company_id' => $companyA->id, 'uuid' => 'A']);
        $this->createLog(['company_id' => $companyB->id, 'uuid' => 'B']);

        $user = User::factory()->create();
        $user->assignRole('accounting');
        $user->companies()->attach($companyA->id);

        $response = $this->actingAs($user)->getJson(route('cfdi-validation-log-reports.data'));

        $response->assertOk();
        $this->assertCount(1, $response->json('rows'));
        $this->assertSame('A', $response->json('rows.0.uuid'));
    }

    public function test_export_sanitizes_formula_injection_from_untrusted_xml_fields(): void
    {
        $role = Role::findOrCreate('accounting', 'web');
        $role->givePermissionTo(['reportes.cfdi_validation_log.ver', 'reportes.cfdi_validation_log.exportar']);
        $user = User::factory()->create();
        $user->assignRole('accounting');

        $this->createLog([
            'uuid' => '=1+1',
            'issuer_name' => '=HYPERLINK("http://evil")',
        ]);

        $response = $this->actingAs($user)->get(route('cfdi-validation-log-reports.export', 'csv'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringNotContainsString(',=1+1,', $content);
        $this->assertStringContainsString("'=1+1", $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_export_without_exportar_permission_gets_403(): void
    {
        $role = Role::findOrCreate('report_viewer', 'web');
        $role->givePermissionTo('reportes.cfdi_validation_log.ver');

        $user = User::factory()->create();
        $user->assignRole('report_viewer');

        $this->actingAs($user)->get(route('cfdi-validation-log-reports.export', 'csv'))->assertForbidden();
    }

    public function test_supplier_mis_cargas_only_shows_its_own_attempts(): void
    {
        $company = Company::factory()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();
        $this->grantPortalAccess($supplierA);

        $this->createLog(['company_id' => $company->id, 'uuid' => 'MINE', 'supplier_id' => $supplierA->id]);
        $this->createLog(['company_id' => $company->id, 'uuid' => 'THEIRS', 'supplier_id' => $supplierB->id]);

        $response = $this->actingAs($supplierA, 'supplier')->get(route('supplier.cfdi-log.index'));

        $response->assertOk();
        $response->assertSee('MINE');
        $response->assertDontSee('THEIRS');
    }

    private function grantPortalAccess(Supplier $supplier): void
    {
        SupplierDocumentType::where('is_required', true)->where('is_active', true)->get()
            ->each(fn (SupplierDocumentType $type) => SupplierDocumentRequirement::create([
                'supplier_id' => $supplier->id,
                'supplier_document_type_id' => $type->id,
                'is_enforced' => true,
                'status' => 'pending',
                'due_at' => now()->addDays(14),
            ]));
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
