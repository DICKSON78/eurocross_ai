<?php

namespace Database\Factories;

use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EuroCrossTransaction>
 */
class EuroCrossTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => EuroCrossSettings::factory(),
            'provider' => 'stripe',
            'event_id' => 'evt_'.fake()->unique()->numerify('########'),
            'transaction_id' => 'txn_'.fake()->numerify('########'),
            'type' => 'subscription',
            'status' => 'paid',
            'plan' => 'growth',
            'amount' => 25.00,
            'vat_amount' => 0.00,
            'currency' => 'EUR',
            'country' => 'DE',
            'payload' => [],
            'occurred_at' => now(),
        ];
    }
}
