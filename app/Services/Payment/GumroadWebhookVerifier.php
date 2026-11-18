<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;

/**
 * Verifies Gumroad pings.
 *
 * Verify the X-Gumroad-Signature header against an HMAC-SHA256 digest of the
 * raw request body. Several common encodings are accepted defensively, but a
 * secret supplied in the query string is never accepted because URLs are
 * routinely stored in logs, proxies, and browser history.
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

        return false;
    }
}
