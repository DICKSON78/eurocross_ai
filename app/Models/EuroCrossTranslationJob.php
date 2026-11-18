<?php

namespace App\Models;

use Database\Factories\EuroCrossTranslationJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossTranslationJob extends Model
{
    /** @use HasFactory<EuroCrossTranslationJobFactory> */
    use HasFactory;

    protected $table = 'eurocross_translation_jobs';

    /** @var array<int, string> */
    protected $fillable = [
        'shop_id',
        'translation_id',
        'batch_id',
        'status',
        'attempts',
        'last_error',
        'queued_at',
        'started_at',
        'finished_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'attempts' => 'integer',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(EuroCrossTranslation::class, 'translation_id');
    }

    /**
     * @param  Builder<EuroCrossTranslationJob>  $query
     * @return Builder<EuroCrossTranslationJob>
     */
    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => 'processing',
            'attempts' => $this->attempts + 1,
            'started_at' => now(),
        ])->save();
    }

    public function markCompleted(): void
    {
        $this->forceFill([
            'status' => 'completed',
            'finished_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
            'last_error' => mb_substr($error, 0, 2000),
        ])->save();
    }
}
