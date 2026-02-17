<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestPaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestPaymentMethod>
 */
class TestPaymentMethodFactory extends Factory
{
    protected $model = TestPaymentMethod::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['card', 'crypto', 'bank', 'paypal', 'other']);

        return [
            'project_id' => Project::factory(),
            'name' => fake()->sentence(2),
            'type' => $type,
            'system' => fake()->optional()->randomElement(['Stripe', 'PayPal', 'Square', 'Braintree']),
            'credentials' => $this->credentialsForType($type),
            'environment' => fake()->randomElement(['develop', 'staging', 'production', null]),
            'is_valid' => true,
            'description' => fake()->optional()->sentence(),
            'tags' => null,
            'created_by' => User::factory(),
            'order' => 0,
        ];
    }

    /**
     * Generate type-specific credentials.
     *
     * @return array<string, string>
     */
    private function credentialsForType(string $type): array
    {
        return match ($type) {
            'card' => [
                'card_number' => fake()->creditCardNumber(),
                'expiry' => fake()->creditCardExpirationDateString(),
                'cvv' => (string) fake()->numberBetween(100, 999),
                'cardholder' => fake()->name(),
            ],
            'crypto' => [
                'wallet_address' => '0x'.fake()->sha256(),
                'network' => fake()->randomElement(['Ethereum', 'Bitcoin', 'Solana']),
            ],
            'bank' => [
                'account_number' => (string) fake()->numberBetween(10000000, 99999999),
                'routing_number' => (string) fake()->numberBetween(100000000, 999999999),
                'bank_name' => fake()->company(),
            ],
            'paypal' => [
                'email' => fake()->safeEmail(),
            ],
            default => [
                'note' => fake()->sentence(),
            ],
        };
    }

    /**
     * Mark the payment method as invalid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_valid' => false,
        ]);
    }
}
