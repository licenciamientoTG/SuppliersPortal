<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\User;
use App\Enum\PaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(), // Crea un usuario automáticamente
            'company_name' => $this->faker->company(),
            'rfc' => $this->faker->unique()->regexify('[A-Z]{3,4}[0-9]{6}[A-Z0-9]{3}'),
            'address' => $this->faker->address(),
            'phone_number' => $this->faker->numerify('##########'),
            // AQUÍ ESTÁ LA MAGIA: 
            // Usamos una función que recibe los atributos ya generados (como user_id)
            'email' => function (array $attributes) {
                return User::find($attributes['user_id'])->email;
            },
            'contact_person' => $this->faker->name(),
            'contact_phone' => $this->faker->numerify('##########'),
            'supplier_type' => $this->faker->randomElement(['product', 'service', 'both']),
            'person_type' => 'moral',
            'tax_regimes' => [
                ['code' => '601', 'label' => 'General de Ley Personas Morales'],
            ],
            'bank_name' => $this->faker->randomElement(['BBVA', 'Santander', 'Banorte']),
            'account_number' => $this->faker->unique()->numerify('##########'),
            'clabe' => $this->faker->numerify('##################'),
            'currency' => 'MXN',
            'default_payment_terms' => $this->faker->randomElement(array_column(PaymentTerm::cases(), 'value')),
            'status' => 'approved',
            'provides_specialized_services' => false,
            'economic_activity' => [$this->faker->jobTitle()],
        ];
    }

    // Estado para proveedores internacionales
    public function international()
    {
        return $this->state(fn(array $attributes) => [
            'currency' => 'USD',
            'swift_bic' => $this->faker->swiftBicNumber(),
            'iban' => $this->faker->iban(),
            'bank_address' => $this->faker->address(),
            'aba_routing' => $this->faker->numerify('#########'),
            'us_bank_name' => 'J.P. Morgan Chase',
        ]);
    }

    // Estado para proveedores con REPSE
    public function repse()
    {
        return $this->state(fn(array $attributes) => [
            'provides_specialized_services' => true,
            'repse_registration_number' => 'REPSE-' . $this->faker->numerify('#####'),
            'repse_expiry_date' => now()->addYears(3),
            'specialized_services_types' => ['Limpieza Industrial', 'Mantenimiento de Tanques'],
        ]);
    }
}
