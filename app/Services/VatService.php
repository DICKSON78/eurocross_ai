<?php

namespace App\Services;

use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class VatService
{
    /**
     * EU standard VAT rates plus the United Kingdom for legacy customers.
     *
     * @var array<string, float>
     */
    private array $euVatRates = [
        'AT' => 20.00, 'BE' => 21.00, 'BG' => 20.00, 'HR' => 25.00,
        'CY' => 19.00, 'CZ' => 21.00, 'DK' => 25.00, 'EE' => 20.00,
        'FI' => 24.00, 'FR' => 20.00, 'DE' => 19.00, 'GR' => 24.00,
        'HU' => 27.00, 'IE' => 23.00, 'IT' => 22.00, 'LV' => 21.00,
        'LT' => 21.00, 'LU' => 17.00, 'MT' => 18.00, 'NL' => 21.00,
        'PL' => 23.00, 'PT' => 23.00, 'RO' => 19.00, 'SK' => 20.00,
        'SI' => 22.00, 'ES' => 21.00, 'SE' => 25.00, 'GB' => 20.00,
    ];

    /**
     * @return array<string, float>
     */
    public function getVatRates(): array
    {
        return $this->euVatRates;
    }

    public function getVatRate(string $countryCode, ?string $productCategory = null): float
    {
        return $this->euVatRates[strtoupper($countryCode)] ?? 0.00;
    }

    public function getCountryName(string $countryCode): string
    {
        $names = [
            'AT' => 'Austria', 'BE' => 'Belgium', 'BG' => 'Bulgaria', 'HR' => 'Croatia',
            'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark', 'EE' => 'Estonia',
            'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany', 'GR' => 'Greece',
            'HU' => 'Hungary', 'IE' => 'Ireland', 'IT' => 'Italy', 'LV' => 'Latvia',
            'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MT' => 'Malta', 'NL' => 'Netherlands',
            'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'SK' => 'Slovakia',
            'SI' => 'Slovenia', 'ES' => 'Spain', 'SE' => 'Sweden', 'GB' => 'United Kingdom',
            'TZ' => 'Tanzania', 'US' => 'United States',
        ];

        $code = strtoupper($countryCode);

        return $names[$code] ?? $code;
    }

    /**
     * @return array<string, mixed>
     */
    public function validateVatNumber(string $countryCode, string $vatNumber): array
    {
        $countryCode = strtoupper($countryCode);
        $vatNumber = preg_replace('/[^A-Z0-9]/i', '', $vatNumber) ?? '';

        if (! isset($this->euVatRates[$countryCode]) || $vatNumber === '') {
            return [
                'valid' => false,
                'checked' => false,
                'country_code' => $countryCode,
                'vat_number' => $vatNumber,
                'error' => 'Unsupported VIES country code or empty VAT number.',
            ];
        }

        if (app()->isLocal() && ! config('services.vies.enabled', true)) {
            return [
                'valid' => false,
                'checked' => false,
                'country_code' => $countryCode,
                'vat_number' => $vatNumber,
                'error' => 'VIES validation is disabled in this environment.',
            ];
        }

        return Cache::remember('eurocross:vies:'.$countryCode.':'.$vatNumber, 3600, function () use ($countryCode, $vatNumber): array {
            $envelope = $this->buildViesEnvelope($countryCode, $vatNumber);

            try {
                $response = Http::connectTimeout(3)
                    ->timeout(15)
                    ->withHeaders([
                        'Content-Type' => 'text/xml; charset=utf-8',
                        'SOAPAction' => '"urn:ec.europa.eu:taxud:vies:services:checkVat:types#checkVat"',
                    ])
                    ->body($envelope)
                    ->post('https://ec.europa.eu/taxation_customs/vies/services/checkVatService')
                    ->throw();

                return $this->parseViesResponse($response->body(), $countryCode, $vatNumber);
            } catch (Throwable $exception) {
                Log::warning('VIES validation request failed', [
                    'country' => $countryCode,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'valid' => false,
                    'checked' => false,
                    'country_code' => $countryCode,
                    'vat_number' => $vatNumber,
                    'error' => 'VIES service temporarily unavailable. Retry later.',
                ];
            }
        });
    }

    /**
     * @return array{chargeVat: bool, rate: float, reason: string, vies_checked?: bool}
     */
    public function shouldChargeVat(
        string $customerCountry,
        string $merchantCountry,
        ?string $customerVatNumber = null,
        bool $useOss = true,
    ): array {
        $customerCountry = strtoupper($customerCountry);
        $merchantCountry = strtoupper($merchantCountry);

        if ($customerVatNumber) {
            $vatNumber = preg_replace('/[^A-Z0-9]/i', '', $customerVatNumber) ?? '';
            $countryCode = strtoupper(substr($vatNumber, 0, 2));
            $number = substr($vatNumber, 2);
            $vies = $this->validateVatNumber($countryCode, $number);

            if (($vies['valid'] ?? false) === true) {
                return [
                    'chargeVat' => false,
                    'rate' => 0.00,
                    'reason' => 'Reverse charge (B2B) - customer VAT number validated by VIES',
                    'vies_checked' => true,
                ];
            }

            if (($vies['checked'] ?? false) === false) {
                Log::warning('VAT validation unavailable; charging standard rate', [
                    'country' => $customerCountry,
                    'error' => $vies['error'] ?? 'Unknown VIES error',
                ]);
            }
        }

        if ($merchantCountry === $customerCountry) {
            $rate = $this->getVatRate($customerCountry);

            return [
                'chargeVat' => $rate > 0,
                'rate' => $rate,
                'reason' => $rate > 0 ? 'Domestic sale - VAT charged at local rate' : 'Domestic sale - no VAT rate configured',
            ];
        }

        if ($useOss && isset($this->euVatRates[$customerCountry])) {
            return [
                'chargeVat' => true,
                'rate' => $this->getVatRate($customerCountry),
                'reason' => 'OSS - VAT charged at customer country rate',
            ];
        }

        if (! isset($this->euVatRates[$customerCountry])) {
            return [
                'chargeVat' => false,
                'rate' => 0.00,
                'reason' => 'Export outside the EU - VAT exempt',
            ];
        }

        return [
            'chargeVat' => true,
            'rate' => $this->getVatRate($merchantCountry),
            'reason' => 'Non-OSS EU sale - VAT charged at merchant country rate',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateOssReport(string $period = 'monthly', ?EuroCrossSettings $shop = null): array
    {
        [$start, $end] = match ($period) {
            'quarterly' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'annual' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $query = EuroCrossTransaction::query()
            ->whereIn('status', ['paid', 'succeeded', 'active'])
            ->whereBetween('occurred_at', [$start, $end]);

        if ($shop !== null) {
            $query->where('shop_id', $shop->id);
        }

        $transactions = $query->get(['country', 'amount', 'vat_amount', 'currency']);
        $byCountry = [];

        foreach ($transactions as $transaction) {
            $country = strtoupper($transaction->country ?? 'Unknown');
            $amount = (float) $transaction->amount;
            $vat = (float) $transaction->vat_amount;

            if (! isset($byCountry[$country])) {
                $byCountry[$country] = [
                    'country_code' => $country,
                    'vat_rate' => $this->getVatRate($country),
                    'transactions' => 0,
                    'net_sales' => 0.0,
                    'vat_amount' => 0.0,
                    'currency' => $transaction->currency,
                ];
            }

            $byCountry[$country]['transactions']++;
            $byCountry[$country]['net_sales'] = round($byCountry[$country]['net_sales'] + $amount - $vat, 2);
            $byCountry[$country]['vat_amount'] = round($byCountry[$country]['vat_amount'] + $vat, 2);
        }

        ksort($byCountry);

        return [
            'period' => $period,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'total_transactions' => $transactions->count(),
            'total_sales' => round($transactions->sum(fn ($transaction) => (float) $transaction->amount - (float) $transaction->vat_amount), 2),
            'total_vat' => round($transactions->sum(fn ($transaction) => (float) $transaction->vat_amount), 2),
            'by_country' => array_values($byCountry),
            'status' => $transactions->isEmpty() ? 'No transactions in this period' : 'Ready for OSS submission',
        ];
    }

    public function formatVatNumber(string $countryCode, string $vatNumber): string
    {
        return strtoupper(substr($countryCode, 0, 2)).preg_replace('/[^A-Z0-9]/i', '', $vatNumber);
    }

    public function detectCountryFromIp(string $ip): string
    {
        return $this->detectCountryDetails($ip)['country'];
    }

    /**
     * @return array{country: string, detected: bool, source: string}
     */
    public function detectCountryDetails(string $ip): array
    {
        $default = (string) config('services.geoip.default_country', 'TZ');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['country' => $default, 'detected' => false, 'source' => 'invalid_ip'];
        }

        return Cache::remember('eurocross:geoip:'.$ip, 86400, function () use ($ip, $default): array {
            $url = str_replace('{ip}', rawurlencode($ip), (string) config('services.geoip.url'));

            try {
                $response = Http::connectTimeout(2)
                    ->timeout(5)
                    ->acceptJson()
                    ->get($url)
                    ->throw();

                $country = strtoupper((string) ($response->json('country_code') ?? $response->json('country') ?? ''));

                if ($country === '') {
                    return ['country' => $default, 'detected' => false, 'source' => 'empty_response'];
                }

                return ['country' => $country, 'detected' => true, 'source' => 'geoip'];
            } catch (Throwable $exception) {
                Log::warning('Geo-IP lookup failed', ['ip' => $ip, 'error' => $exception->getMessage()]);

                return ['country' => $default, 'detected' => false, 'source' => 'fallback'];
            }
        });
    }

    private function buildViesEnvelope(string $countryCode, string $vatNumber): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
            .'xmlns:vies="urn:ec.europa.eu:taxud:vies:services:checkVat:types">'
            .'<soapenv:Header/><soapenv:Body><vies:checkVat>'
            .'<vies:countryCode>'.htmlspecialchars($countryCode, ENT_XML1).'</vies:countryCode>'
            .'<vies:vatNumber>'.htmlspecialchars($vatNumber, ENT_XML1).'</vies:vatNumber>'
            .'</vies:checkVat></soapenv:Body></soapenv:Envelope>';
    }

    /**
     * @return array<string, mixed>
     */
    private function parseViesResponse(string $body, string $countryCode, string $vatNumber): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [
                'valid' => false,
                'checked' => false,
                'country_code' => $countryCode,
                'vat_number' => $vatNumber,
                'error' => 'Invalid VIES response.',
            ];
        }

        $xml->registerXPathNamespace('vies', 'urn:ec.europa.eu:taxud:vies:services:checkVat:types');
        $fault = $xml->xpath('//faultstring');
        $validNodes = $xml->xpath('//vies:valid');
        $nameNodes = $xml->xpath('//vies:name');
        $addressNodes = $xml->xpath('//vies:address');

        if ($fault !== false && $fault !== []) {
            return [
                'valid' => false,
                'checked' => false,
                'country_code' => $countryCode,
                'vat_number' => $vatNumber,
                'error' => 'VIES error: '.(string) $fault[0],
            ];
        }

        if ($validNodes === false || $validNodes === []) {
            return [
                'valid' => false,
                'checked' => false,
                'country_code' => $countryCode,
                'vat_number' => $vatNumber,
                'error' => 'VIES response did not contain a validation result.',
            ];
        }

        $isValid = strtolower(trim((string) $validNodes[0])) === 'true';

        return [
            'valid' => $isValid,
            'checked' => true,
            'country_code' => $countryCode,
            'vat_number' => $vatNumber,
            'business_name' => isset($nameNodes[0]) ? trim((string) $nameNodes[0]) : null,
            'address' => isset($addressNodes[0]) ? trim((string) $addressNodes[0]) : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
