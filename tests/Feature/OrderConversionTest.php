<?php

namespace Tests\Feature;

use App\Livewire\Orders\OrderForm;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderConversionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function accepted_quote_can_be_converted_to_order(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'grand_total' => 2400.00,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OrderForm::class, ['quoteId' => $quote->id]);

        $component->assertSet('customer_id', $customer->id);
        $component->assertSet('total_amount', '2400.00');
    }

    #[Test]
    public function order_tracks_deposit_and_balance(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'reference_number' => 'ORD-TEST-001',
            'status' => 'pending',
            'total_amount' => 2000.00,
            'deposit_required' => 600.00,
            'deposit_paid' => 300.00,
            'balance_due' => 1700.00,
            'staff_user_id' => $user->id,
        ]);

        $this->assertEquals(50.0, $order->deposit_percent);
        $this->assertFalse($order->isFullyPaid());

        $order->update(['deposit_paid' => 600.00, 'balance_due' => 1400.00]);

        $this->assertTrue($order->isFullyPaid());
    }
}
