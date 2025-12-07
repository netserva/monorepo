<?php

declare(strict_types=1);

namespace NetServa\Crm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Crm\Models\CrmClient;

/**
 * @extends Factory<CrmClient>
 */
class CrmClientFactory extends Factory
{
    protected $model = CrmClient::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $name = "{$firstName} {$lastName}";

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => $this->faker->randomElement(['active', 'prospect', 'suspended', 'cancelled']),

            // Personal details
            'first_name' => $firstName,
            'last_name' => $lastName,

            // Business details (randomly included)
            'company_name' => $this->faker->optional(0.4)->company(),
            'abn' => $this->faker->optional(0.3)->passthrough($this->generateAbn()),
            'acn' => $this->faker->optional(0.3)->passthrough($this->generateAcn()),

            // Contact
            'email' => $this->faker->unique()->safeEmail(),
            'home_phone' => $this->faker->optional(0.6)->phoneNumber(),
            'work_phone' => $this->faker->optional(0.5)->phoneNumber(),

            // Address (Australian)
            'address_line_1' => $this->faker->streetAddress(),
            'address_line_2' => $this->faker->optional(0.3)->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->randomElement(['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT']),
            'postcode' => $this->faker->postcode(),
            'country' => 'AU',

            // Metadata
            'notes' => $this->faker->optional(0.4)->paragraph(),
            'metadata' => null,
            'external_id' => $this->faker->optional(0.2)->uuid(),
        ];
    }

    /**
     * Generate a fake but valid-format ABN (11 digits)
     */
    protected function generateAbn(): string
    {
        return sprintf(
            '%02d %03d %03d %03d',
            $this->faker->numberBetween(10, 99),
            $this->faker->numberBetween(100, 999),
            $this->faker->numberBetween(100, 999),
            $this->faker->numberBetween(100, 999)
        );
    }

    /**
     * Generate a fake but valid-format ACN (9 digits)
     */
    protected function generateAcn(): string
    {
        return sprintf(
            '%03d %03d %03d',
            $this->faker->numberBetween(100, 999),
            $this->faker->numberBetween(100, 999),
            $this->faker->numberBetween(100, 999)
        );
    }

    /**
     * Configure the model factory for a business client (with company)
     */
    public function business(): static
    {
        return $this->state(function (array $attributes) {
            $companyName = $this->faker->company();

            return [
                'name' => $companyName,
                'slug' => Str::slug($companyName).'-'.Str::random(5),
                'company_name' => $companyName,
                'abn' => $this->generateAbn(),
                'acn' => $this->generateAcn(),
            ];
        });
    }

    /**
     * Configure the model factory for a personal client (no company)
     */
    public function personal(): static
    {
        return $this->state(function (array $attributes) {
            $firstName = $this->faker->firstName();
            $lastName = $this->faker->lastName();
            $name = "{$firstName} {$lastName}";

            return [
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::random(5),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company_name' => null,
                'abn' => null,
                'acn' => null,
            ];
        });
    }

    /**
     * Configure the model factory for an active client
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Configure the model factory for a prospect client
     */
    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'prospect',
        ]);
    }

    /**
     * Configure the model factory for a suspended client
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Configure the model factory for a cancelled client
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
