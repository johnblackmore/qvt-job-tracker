<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AccountingMappingSettings extends Settings
{
    public array $category_account_codes = [
        'stock' => '5000',
        'equipment' => '5200',
        'travel' => '5300',
        'fuel' => '5301',
        'subsistence' => '5400',
        'utilities' => '5500',
        'professional_fees' => '5600',
        'insurance' => '5700',
        'other' => '5900',
    ];

    public static function group(): string
    {
        return 'accounting';
    }
}
