<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $seedProfiles = [
            [
                'company_name' => 'Proveedor Seed Nacional 01',
                'rfc' => 'PSN010101A01',
                'currency' => 'MXN',
                'provides_specialized_services' => false,
            ],
            [
                'company_name' => 'Proveedor Seed Nacional 02',
                'rfc' => 'PSN010101A02',
                'currency' => 'MXN',
                'provides_specialized_services' => false,
            ],
            [
                'company_name' => 'Proveedor Seed Nacional 03',
                'rfc' => 'PSN010101A03',
                'currency' => 'MXN',
                'provides_specialized_services' => false,
            ],
            [
                'company_name' => 'Proveedor Seed Nacional 04',
                'rfc' => 'PSN010101A04',
                'currency' => 'MXN',
                'provides_specialized_services' => false,
            ],
            [
                'company_name' => 'Proveedor Seed Nacional 05',
                'rfc' => 'PSN010101A05',
                'currency' => 'MXN',
                'provides_specialized_services' => false,
            ],
            [
                'company_name' => 'Proveedor Seed REPSE 01',
                'rfc' => 'PSR010101A01',
                'currency' => 'MXN',
                'provides_specialized_services' => true,
                'repse_registration_number' => 'REPSE-10001',
                'repse_expiry_date' => now()->addYears(3),
                'specialized_services_types' => ['Limpieza Industrial', 'Mantenimiento de Tanques'],
            ],
            [
                'company_name' => 'Proveedor Seed REPSE 02',
                'rfc' => 'PSR010101A02',
                'currency' => 'MXN',
                'provides_specialized_services' => true,
                'repse_registration_number' => 'REPSE-10002',
                'repse_expiry_date' => now()->addYears(3),
                'specialized_services_types' => ['Limpieza Industrial', 'Mantenimiento de Tanques'],
            ],
            [
                'company_name' => 'Proveedor Seed REPSE 03',
                'rfc' => 'PSR010101A03',
                'currency' => 'MXN',
                'provides_specialized_services' => true,
                'repse_registration_number' => 'REPSE-10003',
                'repse_expiry_date' => now()->addYears(3),
                'specialized_services_types' => ['Limpieza Industrial', 'Mantenimiento de Tanques'],
            ],
            [
                'company_name' => 'Proveedor Seed Internacional 01',
                'rfc' => 'PSI010101A01',
                'currency' => 'USD',
                'provides_specialized_services' => false,
                'swift_bic' => 'CHASUS33',
                'iban' => 'GB82WEST12345698765432',
                'bank_address' => '270 Park Avenue, New York, NY',
                'aba_routing' => '021000021',
                'us_bank_name' => 'J.P. Morgan Chase',
            ],
            [
                'company_name' => 'Proveedor Seed Internacional 02',
                'rfc' => 'PSI010101A02',
                'currency' => 'USD',
                'provides_specialized_services' => false,
                'swift_bic' => 'BOFAUS3N',
                'iban' => 'GB82WEST12345698765433',
                'bank_address' => '100 North Tryon Street, Charlotte, NC',
                'aba_routing' => '026009593',
                'us_bank_name' => 'Bank of America',
            ],
        ];

        $availableUsers = User::role('supplier')->doesntHave('supplier')->get();

        if ($availableUsers->isEmpty()) {
            $this->command->warn("No encontre usuarios con rol 'supplier' disponibles. Revisa el UserRoleSeeder.");
            return;
        }

        foreach ($availableUsers->take(count($seedProfiles))->values() as $index => $user) {
            $profile = $seedProfiles[$index];

            $payload = [
                'user_id' => $user->id,
                'company_name' => $profile['company_name'],
                'rfc' => $profile['rfc'],
                'address' => 'Direccion seed ' . ($index + 1),
                'phone_number' => '656000' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'email' => $user->email,
                'contact_person' => $user->name,
                'contact_phone' => '656100' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'supplier_type' => 'both',
                'tax_regime' => 'corporation',
                'bank_name' => 'BBVA',
                'account_number' => '123456' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'clabe' => '0123456789' . str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'currency' => $profile['currency'],
                'default_payment_terms' => 'NET_30',
                'status' => 'approved',
                'provides_specialized_services' => $profile['provides_specialized_services'],
                'economic_activity' => 'Servicios generales',
                'repse_registration_number' => $profile['repse_registration_number'] ?? null,
                'repse_expiry_date' => $profile['repse_expiry_date'] ?? null,
                'specialized_services_types' => $profile['specialized_services_types'] ?? null,
                'swift_bic' => $profile['swift_bic'] ?? null,
                'iban' => $profile['iban'] ?? null,
                'bank_address' => $profile['bank_address'] ?? null,
                'aba_routing' => $profile['aba_routing'] ?? null,
                'us_bank_name' => $profile['us_bank_name'] ?? null,
            ];

            $supplier = Supplier::query()
                ->where('rfc', $profile['rfc'])
                ->orWhere('user_id', $user->id)
                ->first();

            if ($supplier) {
                $supplier->fill($payload);
                $supplier->save();
            } else {
                Supplier::create($payload);
            }
        }

        $user4 = User::find(4);

        if ($user4 && ! $user4->supplier) {
            $payload = [
                'user_id' => $user4->id,
                'company_name' => 'PROVEEDOR DE PRUEBAS UNITARIAS S.A.',
                'rfc' => 'PRUE900101ABC',
                'address' => 'Direccion pruebas unitarias',
                'phone_number' => '6569990000',
                'email' => $user4->email,
                'contact_person' => $user4->name,
                'contact_phone' => '6569990001',
                'supplier_type' => 'both',
                'tax_regime' => 'corporation',
                'bank_name' => 'BBVA',
                'account_number' => '9999000001',
                'clabe' => '012345678900000001',
                'currency' => 'MXN',
                'default_payment_terms' => 'NET_30',
                'status' => 'approved',
                'provides_specialized_services' => false,
                'economic_activity' => 'Pruebas unitarias',
            ];

            $supplier = Supplier::query()
                ->where('rfc', 'PRUE900101ABC')
                ->orWhere('user_id', $user4->id)
                ->first();

            if ($supplier) {
                $supplier->fill($payload);
                $supplier->save();
            } else {
                Supplier::create($payload);
            }
        }
    }
}
