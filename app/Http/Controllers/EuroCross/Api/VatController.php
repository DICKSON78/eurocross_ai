<?php

namespace App\Http\Controllers\EuroCross\Api;

use App\Http\Controllers\Controller;
use App\Models\EuroCrossAuditLog;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossVatSettings;
use App\Services\VatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VatController extends Controller
{
    public function __construct(private VatService $vatService) {}

    public function config(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $vatSettings = $this->vatSettings($settings);

        return response()->json([
            'success' => true,
            'data' => [
                'vat_settings' => $vatSettings,
                'is_oss_enabled' => $vatSettings->use_oss,
                'vies_status' => $vatSettings->viies_status,
                'default_country' => $vatSettings->default_country,
            ],
        ]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'use_oss' => ['sometimes', 'boolean'],
            'default_country' => ['sometimes', 'string', 'size:2'],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:36'],
            'foreign_vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $vatSettings = $this->vatSettings($settings);
        $oldValues = $vatSettings->only(array_keys($validated));
        $vatSettings->fill($validated);
        $vatSettings->save();

        EuroCrossAuditLog::create([
            'shop_id' => $settings->id,
            'action' => 'vat_config_updated',
            'entity_type' => 'eurocross_vat_settings',
            'entity_id' => $vatSettings->id,
            'old_value' => json_encode($oldValues),
            'new_value' => json_encode($vatSettings->only(array_keys($validated))),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['vat_settings' => $vatSettings],
            'message' => 'VAT settings updated.',
        ]);
    }

    public function validateVies(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'vat_number' => ['required', 'string', 'min:5', 'max:36'],
            'country_code' => ['sometimes', 'string', 'size:2'],
        ]);

        $vatNumber = preg_replace('/[^A-Z0-9]/i', '', (string) $validated['vat_number']) ?? '';
        $countryCode = strtoupper((string) ($validated['country_code'] ?? substr($vatNumber, 0, 2)));
        $number = strlen($vatNumber) > 2 && ctype_alpha(substr($vatNumber, 0, 2))
            ? substr($vatNumber, 2)
            : $vatNumber;

        $result = $this->vatService->validateVatNumber($countryCode, $number);

        $status = 'pending';
        if (($result['valid'] ?? false) === true) {
            $status = 'valid';
        } elseif (($result['checked'] ?? false) === false) {
            $status = 'unavailable';
        } elseif (array_key_exists('valid', $result)) {
            $status = 'invalid';
        }

        $vatSettings = $this->vatSettings($settings);
        $vatSettings->forceFill([
            'viies_status' => $status,
            'viies_last_checked' => now(),
            'vat_number' => $this->vatService->formatVatNumber($countryCode, $number),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function getRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['sometimes', 'string', 'size:2'],
        ]);

        $rates = [];
        foreach ($this->vatService->getVatRates() as $code => $rate) {
            if (isset($validated['country']) && strtoupper((string) $validated['country']) !== $code) {
                continue;
            }

            $rates[] = [
                'country_code' => $code,
                'country_name' => $this->vatService->getCountryName($code),
                'rate' => (float) $rate,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $rates,
        ]);
    }

    public function checkVat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['sometimes', 'string', 'size:2'],
            'customer_country' => ['sometimes', 'string', 'size:2'],
            'merchant_country' => ['sometimes', 'string', 'size:2'],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:36'],
            'use_oss' => ['sometimes', 'boolean'],
        ]);

        $settings = $this->shop($request);
        $vatSettings = $this->vatSettings($settings);
        $customerCountry = strtoupper((string) ($validated['country_code'] ?? $validated['customer_country'] ?? ''));

        if ($customerCountry === '') {
            return response()->json([
                'success' => false,
                'error' => 'A country_code or customer_country is required.',
            ], 422);
        }

        $result = $this->vatService->shouldChargeVat(
            $customerCountry,
            strtoupper((string) ($validated['merchant_country'] ?? $vatSettings->default_country)),
            $validated['vat_number'] ?? null,
            (bool) ($validated['use_oss'] ?? $vatSettings->use_oss),
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($result, [
                'charge_vat' => $result['chargeVat'],
                'vat_rate' => $result['rate'],
            ]),
        ]);
    }

    public function generateReport(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'format' => ['sometimes', 'string', 'in:csv,json'],
            'period' => ['sometimes', 'string', 'in:monthly,quarterly,annual'],
        ]);

        $report = $this->vatService->generateOssReport((string) ($validated['period'] ?? 'monthly'), $settings);
        $data = ['report' => $report];

        if (($validated['format'] ?? 'json') === 'csv') {
            $data['download_url'] = route('eurocross.report.download', [
                'period' => $report['period'],
                'format' => 'csv',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function downloadReport(Request $request): StreamedResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'format' => ['sometimes', 'string', 'in:csv'],
            'period' => ['sometimes', 'string', 'in:monthly,quarterly,annual'],
        ]);

        $report = $this->vatService->generateOssReport((string) ($validated['period'] ?? 'monthly'), $settings);
        $filename = 'eurocross-oss-'.$report['period'].'-'.$report['period_end'].'.csv';

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w+');

            fputcsv($handle, ['Country', 'VAT rate', 'Transactions', 'Net sales', 'VAT amount', 'Currency']);

            foreach ($report['by_country'] as $row) {
                fputcsv($handle, [
                    $row['country_code'],
                    $row['vat_rate'],
                    $row['transactions'],
                    $row['net_sales'],
                    $row['vat_amount'],
                    $row['currency'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function vatSettings(EuroCrossSettings $settings): EuroCrossVatSettings
    {
        return EuroCrossVatSettings::firstOrCreate(
            ['shop_id' => $settings->id],
            ['default_country' => 'TZ', 'viies_status' => 'pending'],
        );
    }

    private function shop(Request $request): EuroCrossSettings
    {
        $settings = $request->attributes->get('eurocross_settings');

        abort_unless($settings instanceof EuroCrossSettings, 401, 'Authenticated shop context is missing.');

        return $settings;
    }
}
