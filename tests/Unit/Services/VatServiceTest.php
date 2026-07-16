<?php

namespace Tests\Unit\Services;

use App\Services\VatService;
use App\Settings\VatSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VatServiceTest extends TestCase
{
    #[Test]
    public function it_calculates_cost_for_ex_vat_standard_rate(): void
    {
        $settings = $this->createMock(VatSettings::class);
        $settings->method('rateFor')->with('standard')->willReturn(0.20);

        $service = new VatService($settings);

        $cost = $service->calculateTrueCost(100.00, false, 'standard');

        $this->assertEquals(120.00, $cost);
    }

    #[Test]
    public function it_calculates_cost_for_inc_vat_price(): void
    {
        $settings = $this->createMock(VatSettings::class);

        $service = new VatService($settings);

        $cost = $service->calculateTrueCost(120.00, true, 'standard');

        $this->assertEquals(120.00, $cost);
    }

    #[Test]
    public function it_calculates_cost_for_ex_vat_reduced_rate(): void
    {
        $settings = $this->createMock(VatSettings::class);
        $settings->method('rateFor')->with('reduced')->willReturn(0.05);

        $service = new VatService($settings);

        $cost = $service->calculateTrueCost(100.00, false, 'reduced');

        $this->assertEquals(105.00, $cost);
    }

    #[Test]
    public function it_calculates_cost_for_ex_vat_zero_rate(): void
    {
        $settings = $this->createMock(VatSettings::class);
        $settings->method('rateFor')->with('zero')->willReturn(0.00);

        $service = new VatService($settings);

        $cost = $service->calculateTrueCost(100.00, false, 'zero');

        $this->assertEquals(100.00, $cost);
    }

    #[Test]
    public function it_returns_vat_rate_from_settings(): void
    {
        $settings = $this->createMock(VatSettings::class);
        $settings->method('rateFor')->with('standard')->willReturn(0.20);

        $service = new VatService($settings);

        $this->assertEquals(0.20, $service->vatRateFor('standard'));
    }

    #[Test]
    public function it_throws_for_unknown_rate_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $settings = $this->createMock(VatSettings::class);
        $settings->method('rateFor')->willThrowException(new \InvalidArgumentException);

        $service = new VatService($settings);
        $service->vatRateFor('unknown');
    }
}
