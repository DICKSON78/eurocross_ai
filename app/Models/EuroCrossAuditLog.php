<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossAuditLog extends Model
{
    use HasFactory;

    protected $table = 'eurocross_audit_logs';

    protected $fillable = [
        'shop_id',
        'action',
        'entity_type',
        'entity_id',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }
}
