<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class EuroCrossSettings extends Model
{
    use HasFactory;

    protected $table = 'eurocross_settings';

    protected $fillable = [
        'user_id',
        'api_key',
        'locale',
        'plan',
        'trial',
        'trial_started_at',
        'trial_expires',
        'grace_expires',
        'active_accounts',
        'monthly_recurring_revenue',
        'monthly_profit',
        'subscription_provider',
        'subscription_id',
        'gumroad_sale_id',
        'subscription_status',
        'subscription_current_period_end',
        'next_billing_date',
        'words_reset_at',
        'customer_id',
        'billing_email',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'trial' => 'boolean',
        'trial_started_at' => 'datetime',
        'trial_expires' => 'datetime',
        'grace_expires' => 'datetime',
        'active_accounts' => 'integer',
        'monthly_recurring_revenue' => 'decimal:2',
        'monthly_profit' => 'decimal:2',
        'subscription_current_period_end' => 'datetime',
        'next_billing_date' => 'datetime',
        'words_reset_at' => 'datetime',
    ];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(EuroCrossApiKey::class, 'shop_id');
    }

    public function vatSettings(): HasMany
    {
        return $this->hasMany(EuroCrossVatSettings::class, 'shop_id');
    }

    public function vatSetting(): HasOne
    {
        return $this->hasOne(EuroCrossVatSettings::class, 'shop_id');
    }

    public function translationUsage(): HasMany
    {
        return $this->hasMany(EuroCrossTranslationUsage::class, 'shop_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(EuroCrossTranslation::class, 'shop_id');
    }

    public function translationJobs(): HasMany
    {
        return $this->hasMany(EuroCrossTranslationJob::class, 'shop_id');
    }

    public function quotaReservations(): HasMany
    {
        return $this->hasMany(EuroCrossQuotaReservation::class, 'shop_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EuroCrossTransaction::class, 'shop_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(EuroCrossAuditLog::class, 'shop_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(EuroCrossChatMessage::class, 'shop_id');
    }

    public function isTrial(): bool
    {
        return $this->trial
            && $this->trial_expires instanceof Carbon
            && now()->lessThan($this->trial_expires);
    }

    public function trialRemainingDays(): int
    {
        if (! $this->isTrial() || ! $this->trial_expires instanceof Carbon) {
            return 0;
        }

        return max(0, (int) $this->trial_expires->diffInDays(now()));
    }

    public function getRemainingTrialDays(): int
    {
        return $this->trialRemainingDays();
    }

    public function hasActiveSubscription(): bool
    {
        if (empty($this->subscription_id)) {
            return false;
        }

        $periodEnd = $this->subscription_current_period_end;

        if ($periodEnd instanceof Carbon && now()->greaterThan($periodEnd)) {
            return false;
        }

        if (in_array($this->subscription_status, ['active', 'trialing'], true)) {
            return true;
        }

        // Cancelled subscriptions keep access until the end of the paid period.
        return $this->subscription_status === 'canceled'
            && $periodEnd instanceof Carbon
            && now()->lessThanOrEqualTo($periodEnd);
    }

    public function gracePeriodEndsAt(): ?Carbon
    {
        if (! $this->trial_expires instanceof Carbon) {
            return null;
        }

        if ($this->grace_expires instanceof Carbon) {
            return $this->grace_expires;
        }

        return $this->trial_expires->copy()->addDays((int) config('payment.trial.grace_period_days', 3));
    }

    public function isInGracePeriod(): bool
    {
        if ($this->hasActiveSubscription()) {
            return false;
        }

        if ($this->subscription_status === 'past_due'
            && $this->grace_expires instanceof Carbon
            && now()->lessThanOrEqualTo($this->grace_expires)) {
            return true;
        }

        if (! $this->trial_expires instanceof Carbon) {
            return false;
        }

        $graceEndsAt = $this->gracePeriodEndsAt();

        return ! $this->isTrial()
            && $graceEndsAt instanceof Carbon
            && now()->greaterThan($this->trial_expires)
            && now()->lessThanOrEqualTo($graceEndsAt);
    }

    public function hasActiveAccess(): bool
    {
        return true;
    }

    public function effectivePlan(): string
    {
        if ($this->hasActiveSubscription() || $this->isTrial() || $this->isInGracePeriod()) {
            return is_array(config("payment.plans.{$this->plan}")) ? (string) $this->plan : 'starter';
        }

        return 'free';
    }

    public function isOnFreePlan(): bool
    {
        return $this->effectivePlan() === 'free';
    }

    /**
     * @return array{allowed: bool, reason: string, status: string}
     */
    public function accessStatus(): array
    {
        if ($this->hasActiveSubscription()) {
            return [
                'allowed' => true,
                'reason' => 'Active subscription',
                'status' => $this->subscription_status,
            ];
        }

        if ($this->isTrial()) {
            return [
                'allowed' => true,
                'reason' => 'Active trial',
                'status' => 'trial',
            ];
        }

        if ($this->isInGracePeriod()) {
            return [
                'allowed' => true,
                'reason' => 'Trial grace period',
                'status' => 'grace',
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'Free plan active',
            'status' => 'free',
        ];
    }

    public function planPrice(): float
    {
        return (float) config("payment.plans.{$this->effectivePlan()}.price", 0);
    }

    public function planWordLimit(): int
    {
        return (int) config("payment.plans.{$this->effectivePlan()}.features.words", 0);
    }

    public function planLanguageLimit(): int
    {
        $plan = $this->effectivePlan();
        $limit = (int) config("payment.plans.{$plan}.features.languages", 0);

        if ($this->isTrial()) {
            return max($limit, 10);
        }

        return $limit;
    }

    public function rateLimit(): int
    {
        return (int) config("payment.plans.{$this->effectivePlan()}.rate_limit", 60);
    }

    public function getPlanDisplayName(): string
    {
        $plan = $this->effectivePlan();

        return ucfirst($plan);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlanFeatures(): array
    {
        $plan = $this->effectivePlan();
        $features = config("payment.plans.{$plan}.features", []);

        return [
            'words' => number_format($this->planWordLimit()).' words/month',
            'languages' => $this->planLanguageLimit().' languages',
            'vat' => data_get($features, 'vat') === 'full' ? 'Full VAT OSS with VIES' : 'Basic VAT OSS support',
            'chatbot' => data_get($features, 'chatbot') ? 'Included' : 'Not included',
            'rate_limit' => $this->rateLimit().' requests/minute',
        ];
    }

    public function trackUsage(int $tokens, int $strings = 0, int $words = 0, float $cost = 0): void
    {
        $this->translationUsage()->create([
            'tokens_used' => $tokens,
            'strings_translated' => $strings,
            'words_translated' => $words,
            'ai_cost' => $cost,
            'usage_date' => now()->toDateString(),
        ]);
    }
}
