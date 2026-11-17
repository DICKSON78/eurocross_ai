<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossTranslationUsage extends Model
{
    use HasFactory;

    protected $table = 'eurocross_translation_usage';

    protected $fillable = [
        'shop_id',
        'tokens_used',
        'strings_translated',
        'words_translated',
        'ai_cost',
        'usage_date',
    ];

    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
            'strings_translated' => 'integer',
            'words_translated' => 'integer',
            'ai_cost' => 'decimal:6',
            'usage_date' => 'date',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }

    /**
     * @return array{openai: float, deepl: float}
     */
    public static function tokenRates(): array
    {
        return [
            'openai' => (float) config('services.openai.cost_per_million_tokens', 0.15),
            'deepl' => (float) config('services.deepl.cost_per_million_characters', 25.00),
        ];
    }

    public static function calculateCost(int $tokens, string $provider = 'openai'): float
    {
        $rate = static::tokenRates()[$provider] ?? static::tokenRates()['openai'];

        return round(($tokens / 1_000_000) * $rate, 6);
    }
}
