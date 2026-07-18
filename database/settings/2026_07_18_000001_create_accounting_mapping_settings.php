<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('accounting.category_account_codes', [
            'stock' => '5000',
            'equipment' => '5200',
            'travel' => '5300',
            'fuel' => '5301',
            'subsistence' => '5400',
            'utilities' => '5500',
            'professional_fees' => '5600',
            'insurance' => '5700',
            'other' => '5900',
        ]);
    }

    public function down(): void
    {
        $this->migrator->delete('accounting.category_account_codes');
    }
};
