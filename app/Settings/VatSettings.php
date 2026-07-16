<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class VatSettings extends Settings
{
    public float $standard_rate = 0.20;

    public float $reduced_rate = 0.05;

    public float $zero_rate = 0.00;

    public static function group(): string
    {
        return 'vat';
    }

    public function rateFor(string $type): float
    {
        return match ($type) {
            'standard' => $this->standard_rate,
            'reduced' => $this->reduced_rate,
            'zero' => $this->zero_rate,
            default => throw new \InvalidArgumentException("Unknown VAT rate type: {$type}"),
        };
    }
}
