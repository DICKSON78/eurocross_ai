<?php

namespace App\Models;

use Database\Factories\EuroCrossQuotaReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EuroCrossQuotaReservation extends Model
{
    /** @use HasFactory<EuroCrossQuotaReservationFactory> */
    use HasFactory;

    protected $table = 'eurocross_quota_reservations';

    /** @var array<int, string> */
    protected $fillable = [
        'shop_id',
        'words',
        'token',
        'status',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'words' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }

    /**
     * @param  Builder<EuroCrossQuotaReservation>  $query
     * @return Builder<EuroCrossQuotaReservation>
     */
    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * @param  Builder<EuroCrossQuotaReservation>  $query
     * @return Builder<EuroCrossQuotaReservation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'reserved')
            ->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->status === 'reserved'
            && $this->expires_at instanceof Carbon
            && $this->expires_at->greaterThan(now());
    }

    public function commit(): void
    {
        if ($this->status === 'reserved') {
            $this->forceFill(['status' => 'committed'])->save();
        }
    }

    public function release(): void
    {
        if ($this->status === 'reserved') {
            $this->forceFill(['status' => 'released'])->save();
        }
    }
}
