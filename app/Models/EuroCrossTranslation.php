<?php

namespace App\Models;

use Database\Factories\EuroCrossTranslationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EuroCrossTranslation extends Model
{
    /** @use HasFactory<EuroCrossTranslationFactory> */
    use HasFactory;

    protected $table = 'eurocross_translations';

    /** @var array<int, string> */
    protected $fillable = [
        'shop_id',
        'source_type',
        'source_id',
        'field',
        'source_locale',
        'target_locale',
        'source_hash',
        'source_text',
        'translated_text',
        'status',
        'manually_edited',
        'provider',
        'retry_count',
        'last_error',
        'words',
        'tokens',
        'cost',
        'batch_id',
        'quota_reservation_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'manually_edited' => 'boolean',
        'retry_count' => 'integer',
        'words' => 'integer',
        'tokens' => 'integer',
        'cost' => 'decimal:6',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }

    public function quotaReservation(): BelongsTo
    {
        return $this->belongsTo(EuroCrossQuotaReservation::class, 'quota_reservation_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(EuroCrossTranslationJob::class, 'translation_id');
    }

    /**
     * @param  Builder<EuroCrossTranslation>  $query
     * @return Builder<EuroCrossTranslation>
     */
    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * @param  Builder<EuroCrossTranslation>  $query
     * @return Builder<EuroCrossTranslation>
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function markProcessing(): void
    {
        $this->forceFill(['status' => 'processing'])->save();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markCompleted(array $result): void
    {
        if ($this->manually_edited) {
            return;
        }

        $this->forceFill([
            'translated_text' => (string) ($result['translated'] ?? ''),
            'status' => 'completed',
            'provider' => (string) ($result['provider'] ?? 'unknown'),
            'tokens' => (int) ($result['tokens'] ?? 0),
            'cost' => (float) ($result['cost'] ?? 0),
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        if ($this->manually_edited) {
            return;
        }

        $this->forceFill([
            'status' => 'failed',
            'last_error' => mb_substr($error, 0, 2000),
            'retry_count' => $this->retry_count + 1,
        ])->save();
    }
}
