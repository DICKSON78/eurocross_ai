<?php

namespace App\Console\Commands;

use App\Models\EuroCrossManualPayment;
use Illuminate\Console\Command;

class ListGumroadManualPayments extends Command
{
    protected $signature = 'gumroad:manual-payments';

    protected $description = 'List unmatched Gumroad payments awaiting manual review';

    public function handle(): int
    {
        $payments = EuroCrossManualPayment::latest()->get(['event_id', 'sale_id', 'email', 'plan', 'status', 'created_at']);

        if ($payments->isEmpty()) {
            $this->info('No manual payments found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Event ID', 'Sale ID', 'Email', 'Plan', 'Status', 'Received'],
            $payments->map(fn (EuroCrossManualPayment $payment): array => [
                $payment->event_id,
                $payment->sale_id ?? '—',
                $payment->email,
                $payment->plan ?? '—',
                $payment->status,
                $payment->created_at?->format('Y-m-d H:i') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
