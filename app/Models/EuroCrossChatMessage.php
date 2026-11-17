<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossChatMessage extends Model
{
    use HasFactory;

    protected $table = 'eurocross_chat_messages';

    protected $fillable = [
        'shop_id',
        'visitor_id',
        'language',
        'role',
        'message',
        'tokens',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }
}
