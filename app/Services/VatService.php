<?php

namespace App\Services;

use App\Settings\VatSettings;

class VatService
{
    public function __construct(
        private VatSettings $vatSettings,
    ) {}

    public function calculateTrueCost(float $tradePrice, bool $includesVat, string $vatRateType): float
    {
        $rate = $this->vatSettings->rateFor($vatRateType);

        if ($includesVat) {
            return $tradePrice;
        }

        return round($tradePrice * (1 + $rate), 2);
    }

    public function vatRateFor(string $type): float
    {
        return $this->vatSettings->rateFor($type);
    }
}
