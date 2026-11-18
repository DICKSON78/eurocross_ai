<?php

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Models\EuroCrossQuotaReservation;
use App\Models\EuroCrossSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class QuotaReservationService
{
    private const RESERVATION_TTL_HOURS = 24;

    /**
     * Atomically reserve words before billable translation work is queued.
     *
     * The shop row is locked so concurrent requests cannot both pass the same
     * quota check. Usage and live reservations are counted in the same
     * transaction that creates the reservation.
     *
     * @throws QuotaExceededException
     */
    public function reserve(EuroCrossSettings $shop, int $words): EuroCrossQuotaReservation
    {
        if ($words <= 0) {
            throw new InvalidArgumentException('A quota reservation must reserve at least one word.');
        }

        return DB::transaction(function () use ($shop, $words): EuroCrossQuotaReservation {
            $lockedShop = EuroCrossSettings::query()
                ->whereKey($shop->id)
                ->lockForUpdate()
                ->firstOrFail();

            $limit = $lockedShop->planWordLimit();

            if ($limit > 0) {
                $start = $this->periodStart($lockedShop);
                $end = now()->endOfMonth()->toDateString();

                $used = (int) $lockedShop->translationUsage()
                    ->whereBetween('usage_date', [$start->toDateString(), $end])
                    ->sum('words_translated');

                $reserved = (int) EuroCrossQuotaReservation::query()
                    ->forShop($lockedShop->id)
                    ->active()
                    ->where('created_at', '>=', $start)
                    ->sum('words');

                if ($used + $reserved + $words > $limit) {
                    throw new QuotaExceededException($used, $limit, $words, $reserved);
                }
            }

            return EuroCrossQuotaReservation::query()->create([
                'shop_id' => $lockedShop->id,
                'words' => $words,
                'token' => (string) Str::uuid(),
                'status' => 'reserved',
                'expires_at' => now()->addHours(self::RESERVATION_TTL_HOURS),
            ]);
        });
    }

    public function commit(?EuroCrossQuotaReservation $reservation): void
    {
        $reservation?->commit();
    }

    public function release(?EuroCrossQuotaReservation $reservation): void
    {
        $reservation?->release();
    }

    private function periodStart(EuroCrossSettings $shop): Carbon
    {
        $monthStart = now()->startOfMonth();

        if ($shop->words_reset_at instanceof Carbon && $shop->words_reset_at->greaterThan($monthStart)) {
            return $shop->words_reset_at->copy()->startOfDay();
        }

        return $monthStart;
    }
}
