<?php

namespace Database\Factories;

use App\Models\EuroCrossQuotaReservation;
use App\Models\EuroCrossSettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EuroCrossQuotaReservation>
 */
class EuroCrossQuotaReservationFactory extends Factory
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
            'words' => fake()->numberBetween(1, 500),
            'token' => (string) Str::uuid(),
            'status' => 'reserved',
            'expires_at' => now()->addHours(6),
        ];
    }
}
