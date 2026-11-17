<?php

namespace Database\Factories;

use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossSettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EuroCrossApiKey>
 */
class EuroCrossApiKeyFactory extends Factory
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
            'provider' => 'openai',
            'key_name' => 'primary',
            'encrypted_key' => 'sk-'.Str::random(24),
            'key_hash' => null,
            'active' => true,
            'last_used_at' => null,
            'daily_usage' => 0,
            'monthly_usage' => 0,
        ];
    }

    public function accessToken(?string $token = null): static
    {
        $rawToken = $token ?? 'ecx_live_'.Str::random(48);

        return $this->state(fn (array $attributes): array => [
            'provider' => 'eurocross',
            'key_name' => 'dashboard',
            'encrypted_key' => $rawToken,
            'key_hash' => hash('sha256', $rawToken),
        ]);
    }
}
