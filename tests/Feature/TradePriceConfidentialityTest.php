<?php

namespace Tests\Feature;

use App\Livewire\Quotes\QuoteShow;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TradePriceConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_facing_quote_show_does_not_display_trade_totals(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'sent',
            'total_retail' => 1500.00,
            'total_trade' => 900.00,
            'grand_total' => 1500.00,
        ]);

        $component = Livewire::actingAs($user)->test(QuoteShow::class, ['id' => $quote->id]);

        $component->assertSee('£1,500.00');
        $component->assertSee('Trade cost');
        $component->assertSee('£900.00');
    }
}
