<?php

namespace Tests\Unit;

use App\Http\Requests\RegisterSupplierRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegisterSupplierRequestTest extends TestCase
{
    public function test_national_legal_entity_does_not_require_names(): void
    {
        $validator = $this->validator([
            'parsed_person_type' => 'moral',
            'first_name' => '',
            'last_name' => '',
        ]);

        $this->assertFalse($validator->errors()->has('first_name'));
        $this->assertFalse($validator->errors()->has('last_name'));
    }

    public function test_national_physical_person_requires_names_when_missing_from_csf(): void
    {
        $validator = $this->validator([
            'parsed_person_type' => 'fisica',
            'first_name' => '',
            'last_name' => '',
        ]);

        $this->assertTrue($validator->errors()->has('first_name'));
        $this->assertTrue($validator->errors()->has('last_name'));
    }

    private function validator(array $overrides)
    {
        $request = new RegisterSupplierRequest;
        $request->merge(array_merge([
            'is_foreign' => false,
            'accepted_prefilled_data' => '1',
            'email' => 'supplier@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Empresa SA de CV',
            'rfc' => '',
            'address' => '',
            'postal_code' => '',
            'csf_upload_token' => 'token',
            'supplier_type' => 'service',
            'phone_number' => '6561234567',
            'contact_person' => 'Contacto',
            'provides_specialized_services' => false,
            'economic_activity' => ['Comercio'],
            'default_payment_terms' => 'contado',
            'accepted_currencies' => ['MXN'],
        ], $overrides));

        $validator = Validator::make($request->all(), []);
        $request->withValidator($validator);
        $validator->passes();

        return $validator;
    }
}
