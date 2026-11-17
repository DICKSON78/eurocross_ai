<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossApiKey extends Model
{
    use HasFactory;

    protected $table = 'eurocross_api_keys';

    protected $fillable = [
        'shop_id',
        'provider',
        'key_name',
        'encrypted_key',
        'key_hash',
        'active',
        'last_used_at',
        'daily_usage',
        'monthly_usage',
    ];

    protected $hidden = [
        'encrypted_key',
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_key' => 'encrypted',
            'active' => 'boolean',
            'last_used_at' => 'datetime',
            'daily_usage' => 'integer',
            'monthly_usage' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }

    public function markUsed(): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'daily_usage' => $this->daily_usage + 1,
            'monthly_usage' => $this->monthly_usage + 1,
        ])->save();
    }
}
