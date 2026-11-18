<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;

/**
 * Verifies Gumroad pings.
 *
 * NOTE: Gumroad's public "Verifying pings" page could not be fetched while
 * implementing this. We use the commonly documented X-Gumroad-Signature
 * header with an HMAC-SHA256 digest of the RAW body, hex encoded. Several
 * encodings are accepted defensively. The ?secret= query parameter is only
 * accepted as a fallback for accounts where "Sign pings" is disabled.
 */
class GumroadWebhookVerifier
{
    public function verify(Request $request): bool
    {
        $secret = (string) config('payment.gumroad.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $raw = $request->getContent();
        $signature = (string) $request->header('X-Gumroad-Signature', '');

        if ($signature !== '') {
            $expected = hash_hmac('sha256', $raw, $secret);
            $candidates = [
                $signature,
                preg_replace('/^sha256=/i', '', $signature),
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && hash_equals($expected, strtolower($candidate))) {
                    return true;
                }

                if (is_string($candidate) && hash_equals(base64_encode(hex2bin($expected) ?: ''), $candidate)) {
                    return true;
                }
            }

            return false;
        }

        // Fallback: only used when signing is disabled on Gumroad.
        return hash_equals($secret, (string) $request->query('secret', ''));
    }
}
