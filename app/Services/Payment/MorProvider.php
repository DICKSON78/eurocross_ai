<?php

namespace App\Services\Payment;

interface MorProvider
{
    /**
     * Create a checkout session.
     */
    public function createCheckout(array $data): array;

    /**
     * Get subscription details.
     */
    public function getSubscription(string $id): array;

    /**
     * Change subscription plan.
     */
    public function changePlan(string $subscriptionId, string $newPlan): array;

    /**
     * Cancel subscription.
     */
    public function cancel(string $subscriptionId): array;

    /**
     * Get customer portal URL.
     */
    public function portalUrl(string $customerId): string;

    /**
     * Verify webhook signature.
     */
    public function verifyWebhook(string $payload, string $signature): bool;
}
