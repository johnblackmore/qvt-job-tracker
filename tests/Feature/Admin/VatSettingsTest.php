<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\VatSettings;
use App\Models\User;
use App\Settings\VatSettings as VatSettingsConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VatSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    #[Test]
    public function admin_can_view_vat_settings_page(): void
    {
        Livewire::actingAs($this->admin)
            ->test(VatSettings::class)
            ->assertSet('standard_rate', 20)
            ->assertSet('reduced_rate', 5)
            ->assertSet('zero_rate', 0);
    }

    #[Test]
    public function admin_can_update_vat_rates(): void
    {
        Livewire::actingAs($this->admin)
            ->test(VatSettings::class)
            ->set('standard_rate', 20)
            ->set('reduced_rate', 6)
            ->set('zero_rate', 0)
            ->call('save')
            ->assertDispatched('notify');

        $settings = app(VatSettingsConfig::class);
        $this->assertEquals(0.20, $settings->standard_rate);
        $this->assertEquals(0.06, $settings->reduced_rate);
        $this->assertEquals(0.00, $settings->zero_rate);
    }

    #[Test]
    public function rates_must_be_valid_percentages(): void
    {
        Livewire::actingAs($this->admin)
            ->test(VatSettings::class)
            ->set('standard_rate', -1)
            ->call('save')
            ->assertHasErrors('standard_rate');
    }
}
