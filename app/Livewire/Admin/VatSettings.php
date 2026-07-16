<?php

namespace App\Livewire\Admin;

use App\Settings\VatSettings as VatSettingsConfig;
use Livewire\Component;

class VatSettings extends Component
{
    public float $standard_rate = 20;

    public float $reduced_rate = 5;

    public float $zero_rate = 0;

    public function mount(): void
    {
        $settings = app(VatSettingsConfig::class);
        $this->standard_rate = $settings->standard_rate * 100;
        $this->reduced_rate = $settings->reduced_rate * 100;
        $this->zero_rate = $settings->zero_rate * 100;
    }

    public function save(): void
    {
        $this->validate([
            'standard_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'reduced_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'zero_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $settings = app(VatSettingsConfig::class);
        $settings->standard_rate = $this->standard_rate / 100;
        $settings->reduced_rate = $this->reduced_rate / 100;
        $settings->zero_rate = $this->zero_rate / 100;
        $settings->save();

        $this->dispatch('notify', message: 'VAT rates saved.', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.vat-settings');
    }
}
