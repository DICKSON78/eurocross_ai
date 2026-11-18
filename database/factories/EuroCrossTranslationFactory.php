<?php

namespace Database\Factories;

use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EuroCrossTranslation>
 */
class EuroCrossTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sourceText = fake()->sentence();

        return [
            'shop_id' => EuroCrossSettings::factory(),
            'source_type' => 'product',
            'source_id' => (string) fake()->numberBetween(1, 100000),
            'field' => fake()->randomElement(['title', 'description']),
            'source_locale' => 'en',
            'target_locale' => 'fr',
            'source_hash' => hash('sha256', $sourceText),
            'source_text' => $sourceText,
            'translated_text' => null,
            'status' => 'pending',
            'manually_edited' => false,
            'provider' => null,
            'retry_count' => 0,
            'last_error' => null,
            'words' => str_word_count($sourceText),
            'tokens' => 0,
            'cost' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'translated_text' => '[fr] '.$attributes['source_text'],
            'status' => 'completed',
            'provider' => 'mock',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'last_error' => 'Provider unavailable.',
            'retry_count' => 1,
        ]);
    }
}
