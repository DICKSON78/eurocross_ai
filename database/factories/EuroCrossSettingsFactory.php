<?php

namespace Database\Factories;

use App\Models\EuroCrossSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EuroCrossSettings>
 */
class EuroCrossSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'en',
            'plan' => 'starter',
            'trial' => true,
            'trial_started_at' => now()->subDays(2),
            'trial_expires' => now()->addDays(12),
            'grace_expires' => now()->addDays(15),
            'subscription_provider' => null,
            'subscription_id' => null,
            'subscription_status' => 'none',
            'subscription_current_period_end' => null,
            'customer_id' => null,
            'billing_email' => fake()->safeEmail(),
            'active_accounts' => 0,
            'monthly_recurring_revenue' => 0,
            'monthly_profit' => 0,
        ];
    }

    public function expiredTrial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'trial' => true,
            'trial_started_at' => now()->subDays(20),
            'trial_expires' => now()->subDays(6),
            'grace_expires' => now()->subDays(3),
        ]);
    }

    public function activeSubscription(string $plan = 'growth'): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan' => $plan,
            'trial' => false,
            'trial_expires' => null,
            'grace_expires' => null,
            'subscription_provider' => 'stripe',
            'subscription_id' => 'sub_'.fake()->unique()->numerify('########'),
            'subscription_status' => 'active',
            'subscription_current_period_end' => now()->addMonth(),
        ]);
    }
}
