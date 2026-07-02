<?php

namespace Database\Factories;

use App\Enum\PaymentTerm;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Password123!',
            'is_active' => true,
            'company_name' => $this->faker->company(),
            'rfc' => $this->faker->unique()->regexify('[A-Z]{3,4}[0-9]{6}[A-Z0-9]{3}'),
            'address' => $this->faker->address(),
            'postal_code' => $this->faker->numerify('#####'),
            'phone_number' => $this->faker->numerify('##########'),
            'contact_person' => trim($firstName . ' ' . $lastName),
            'contact_phone' => $this->faker->numerify('##########'),
            'supplier_type' => $this->faker->randomElement(['product', 'service', 'product_service']),
            'person_type' => 'moral',
            'tax_regimes' => [
                ['code' => '601', 'label' => 'General de Ley Personas Morales'],
            ],
            'bank_name' => $this->faker->randomElement(['BBVA', 'Santander', 'Banorte']),
            'account_number' => $this->faker->unique()->numerify('##########'),
            'clabe' => $this->faker->numerify('##################'),
            'currency' => 'MXN',
            'default_payment_terms' => $this->faker->randomElement(array_column(PaymentTerm::cases(), 'value')),
            'approval_status' => 'approved',
            'document_status' => 'approved',
            'provides_specialized_services' => false,
            'economic_activity' => [$this->faker->jobTitle()],
        ];
    }

    public function international()
    {
        return $this->state(fn () => [
            'currency' => 'USD',
            'swift_bic' => Str::upper($this->faker->lexify('????????')),
            'iban' => Str::upper($this->faker->bothify('GB##WEST##########')),
            'bank_address' => $this->faker->address(),
            'aba_routing' => $this->faker->numerify('#########'),
            'us_bank_name' => 'J.P. Morgan Chase',
        ]);
    }

    public function repse()
    {
        return $this->state(fn () => [
            'provides_specialized_services' => true,
            'repse_registration_number' => 'REPSE-' . $this->faker->numerify('#####'),
            'repse_expiry_date' => now()->addYears(3),
            'specialized_services_types' => ['Limpieza Industrial', 'Mantenimiento de Tanques'],
        ]);
    }

    public function external()
    {
        return $this->state(fn () => [
            'is_external' => true,
            'is_active' => false,
            'password' => Str::random(40),
        ]);
    }
}
