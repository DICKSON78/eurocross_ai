<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossWebhook extends Model
{
    use HasFactory;

    protected $table = 'eurocross_webhooks';

    protected $fillable = [
        'shop_id',
        'provider',
        'event',
        'event_id',
        'payload',
        'processed',
        'processed_at',
        'signature',
        'error',
        'retry_count',
        'last_retry_at',
    ];

    protected $hidden = [
        'signature',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
            'last_retry_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }

    public function providerName(): string
    {
        return match ($this->provider) {
            'lemon_squeezy' => 'Lemon Squeezy',
            'stripe' => 'Stripe',
            default => $this->provider,
        };
    }

    public function markProcessed(): void
    {
        $this->forceFill([
            'processed' => true,
            'processed_at' => now(),
            'error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'processed' => false,
            'error' => $error,
            'last_retry_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ])->save();
    }

    public function canRetry(): bool
    {
        return $this->retry_count < 3 && ! $this->processed;
    }
}
