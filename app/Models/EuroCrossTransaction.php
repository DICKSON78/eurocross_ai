<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossTransaction extends Model
{
    use HasFactory;

    protected $table = 'eurocross_transactions';

    protected $fillable = [
        'shop_id',
        'provider',
        'event_id',
        'transaction_id',
        'type',
        'status',
        'plan',
        'amount',
        'vat_amount',
        'currency',
        'country',
        'payload',
        'occurred_at',
    ];

    protected $hidden = [
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }
}
