<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('vat.standard_rate', 0.20);
        $this->migrator->add('vat.reduced_rate', 0.05);
        $this->migrator->add('vat.zero_rate', 0.00);
    }

    public function down(): void
    {
        $this->migrator->delete('vat.standard_rate');
        $this->migrator->delete('vat.reduced_rate');
        $this->migrator->delete('vat.zero_rate');
    }
};
