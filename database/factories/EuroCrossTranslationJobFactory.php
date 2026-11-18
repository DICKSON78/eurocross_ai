<?php

namespace Database\Factories;

use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTranslation;
use App\Models\EuroCrossTranslationJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EuroCrossTranslationJob>
 */
class EuroCrossTranslationJobFactory extends Factory
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
            'translation_id' => EuroCrossTranslation::factory(),
            'batch_id' => null,
            'status' => 'queued',
            'attempts' => 0,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (EuroCrossTranslationJob $job): void {
            $job->forceFill(['shop_id' => $job->translation->shop_id])->save();
        });
    }
}
