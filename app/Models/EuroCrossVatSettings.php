<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EuroCrossVatSettings extends Model
{
    use HasFactory;

    protected $table = 'eurocross_vat_settings';

    protected $fillable = [
        'shop_id',
        'default_country',
        'vat_number',
        'use_oss',
        'viies_status',
        'viies_last_checked',
        'foreign_vat_rate',
    ];

    protected function casts(): array
    {
        return [
            'use_oss' => 'boolean',
            'viies_last_checked' => 'datetime',
            'foreign_vat_rate' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EuroCrossSettings::class, 'shop_id');
    }

    public function isViesActive(): bool
    {
        return $this->viies_status === 'valid';
    }

    public function getViesMessage(): string
    {
        return match ($this->viies_status) {
            'valid' => 'VAT number validated via VIES',
            'invalid' => 'VAT number invalid - VAT applies',
            'pending' => 'VAT number pending validation',
            'unavailable' => 'VIES temporarily unavailable - retry later',
            default => 'VAT number not validated',
        };
    }

    /**
     * @return array{charge_vat: bool, rate: float, reason: string}
     */
    public function shouldChargeVat(string $customerCountry, ?string $customerVatNumber = null): array
    {
        if ($customerVatNumber && $this->viies_status === 'valid') {
            return [
                'charge_vat' => false,
                'rate' => 0.0,
                'reason' => 'Reverse charge - VAT applied at customer rate',
            ];
        }

        if ($this->use_oss) {
            return [
                'charge_vat' => true,
                'rate' => (float) $this->foreign_vat_rate,
                'reason' => 'OSS - VAT charged at configured customer country rate',
            ];
        }

        return [
            'charge_vat' => false,
            'rate' => 0.0,
            'reason' => 'VAT not charged for this configuration',
        ];
    }
}
