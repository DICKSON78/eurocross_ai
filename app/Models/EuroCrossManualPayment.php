<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossManualPayment extends Model
{
    use HasFactory;

    protected $table = 'eurocross_manual_payments';

    protected $fillable = [
        'event_id',
        'sale_id',
        'subscription_id',
        'email',
        'plan',
        'product_id',
        'permalink',
        'note',
        'shop_id',
        'status',
        'linked_by',
        'linked_at',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'linked_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
