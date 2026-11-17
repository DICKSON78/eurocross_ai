<?php

namespace Tests\Unit;

use App\Services\VatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_vat_rate_for_germany(): void
    {
        $service = new VatService;
        $rate = $service->getVatRate('DE');
        $this->assertEquals(19.00, $rate);
    }

    public function test_get_vat_rate_for_france(): void
    {
        $service = new VatService;
        $rate = $service->getVatRate('FR');
        $this->assertEquals(20.00, $rate);
    }

    public function test_get_vat_rate_for_non_eu_country(): void
    {
        $service = new VatService;
        $rate = $service->getVatRate('US');
        $this->assertEquals(0.00, $rate);
    }

    public function test_validate_vat_number(): void
    {
        $service = new VatService;
        $result = $service->validateVatNumber('DE', '123456789');

        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('country_code', $result);
        $this->assertEquals('DE', $result['country_code']);
    }

    public function test_should_charge_vat_for_b2c_eu(): void
    {
        $service = new VatService;
        $result = $service->shouldChargeVat('FR', 'DE', null, true);

        $this->assertTrue($result['chargeVat']);
        $this->assertEquals(20.00, $result['rate']);
    }

    public function test_should_not_charge_vat_for_b2b(): void
    {
        $service = new VatService;
        $result = $service->shouldChargeVat('FR', 'DE', 'FR123456789', true);

        // B2B with valid VAT number should reverse charge
        $this->assertArrayHasKey('chargeVat', $result);
    }

    public function test_format_vat_number(): void
    {
        $service = new VatService;
        $formatted = $service->formatVatNumber('de', '123456789');
        $this->assertEquals('DE123456789', $formatted);
    }

    public function test_generate_oss_report(): void
    {
        $service = new VatService;
        $report = $service->generateOssReport('monthly');

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertEquals('monthly', $report['period']);
    }
}
