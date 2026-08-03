<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Services\SupplierDocumentRequirementService;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $seedProfiles = [
            ['company_name' => 'Proveedor Seed Nacional 01', 'rfc' => 'PSN010101A01', 'currency' => 'MXN', 'provides_specialized_services' => false],
            ['company_name' => 'Proveedor Seed Nacional 02', 'rfc' => 'PSN010101A02', 'currency' => 'MXN', 'provides_specialized_services' => false],
            ['company_name' => 'Proveedor Seed Nacional 03', 'rfc' => 'PSN010101A03', 'currency' => 'MXN', 'provides_specialized_services' => false],
            ['company_name' => 'Proveedor Seed REPSE 01', 'rfc' => 'PSR010101A01', 'currency' => 'MXN', 'provides_specialized_services' => true, 'repse_registration_number' => 'REPSE-10001', 'repse_expiry_date' => now()->addYears(3), 'specialized_services_types' => ['Limpieza Industrial', 'Mantenimiento de Tanques']],
            ['company_name' => 'Proveedor Seed Internacional 01', 'rfc' => 'PSI010101A01', 'currency' => 'USD', 'provides_specialized_services' => false, 'swift_bic' => 'CHASUS33', 'iban' => 'GB82WEST12345698765432', 'bank_address' => '270 Park Avenue, New York, NY', 'aba_routing' => '021000021', 'us_bank_name' => 'J.P. Morgan Chase'],
        ];

        foreach ($seedProfiles as $index => $profile) {
            $supplier = Supplier::updateOrCreate(
                ['rfc' => $profile['rfc']],
                [
                    'first_name' => 'Proveedor',
                    'last_name' => 'Seed ' . ($index + 1),
                    'email' => 'proveedor.seed.' . ($index + 1) . '@example.com',
                    'password' => 'Password123!',
                    'is_active' => true,
                    'company_name' => $profile['company_name'],
                    'address' => 'Dirección seed ' . ($index + 1),
                    'postal_code' => str_pad((string) (32000 + $index), 5, '0', STR_PAD_LEFT),
                    'phone_number' => '656000' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'contact_person' => 'Proveedor Seed ' . ($index + 1),
                    'contact_phone' => '656100' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'supplier_type' => 'product_service',
                    'person_type' => 'moral',
                    'tax_regimes' => [['code' => '601', 'label' => 'General de Ley Personas Morales']],
                    'bank_name' => 'BBVA',
                    'account_number' => '123456' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'clabe' => '0123456789' . str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    'currency' => $profile['currency'],
                    'default_payment_terms' => 'NET_30',
                    'approval_status' => 'approved',
                    'document_status' => 'approved',
                    'approved_at' => now(),
                    'provides_specialized_services' => $profile['provides_specialized_services'],
                    'economic_activity' => ['Servicios generales'],
                    'repse_registration_number' => $profile['repse_registration_number'] ?? null,
                    'repse_expiry_date' => $profile['repse_expiry_date'] ?? null,
                    'specialized_services_types' => $profile['specialized_services_types'] ?? null,
                    'swift_bic' => $profile['swift_bic'] ?? null,
                    'iban' => $profile['iban'] ?? null,
                    'bank_address' => $profile['bank_address'] ?? null,
                    'aba_routing' => $profile['aba_routing'] ?? null,
                    'us_bank_name' => $profile['us_bank_name'] ?? null,
                ]
            );

            $this->approveRequiredDocuments($supplier);
        }
    }

    private function approveRequiredDocuments(Supplier $supplier): void
    {
        $requirements = app(SupplierDocumentRequirementService::class)->ensureForSupplier($supplier);

        foreach ($requirements->where('is_enforced', true) as $requirement) {
            $type = $requirement->documentType;

            if (! $type) {
                continue;
            }

            $document = SupplierDocument::updateOrCreate(
                [
                    'supplier_document_requirement_id' => $requirement->id,
                    'path_file' => "seed://supplier-documents/{$type->code}",
                ],
                [
                    'supplier_id' => $supplier->id,
                    'supplier_document_type_id' => $type->id,
                    'doc_type' => $type->code,
                    'status' => 'accepted',
                    'uploaded_at' => now(),
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ]
            );

            $requirement->update([
                'current_document_id' => $document->id,
                'status' => 'compliant',
                'due_at' => null,
                'expires_at' => null,
                'fulfilled_at' => now(),
            ]);
        }

        $supplier->recalculateDocumentStatus();
    }
}
