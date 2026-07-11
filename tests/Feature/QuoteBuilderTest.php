<?php

namespace Tests\Feature;

use App\Livewire\Quotes\QuoteBuilder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function staff_can_create_quote_with_line_items(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'retail_price' => 299.99,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(QuoteBuilder::class)
            ->set('customer_id', $customer->id)
            ->set('reference_number', 'Q-TEST-001')
            ->set('status', 'draft')
            ->call('addProductLine', $product->id)
            ->set('lineItems.0.quantity', '2')
            ->set('lineItems.0.unit_retail_price', '299.99')
            ->call('save')
            ->assertRedirect(route('quotes.index'));

        $this->assertDatabaseHas('quotes', [
            'reference_number' => 'Q-TEST-001',
            'customer_id' => $customer->id,
            'grand_total' => 599.98,
        ]);
    }

    #[Test]
    public function trade_prices_are_stored_internally(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        Livewire::actingAs($user)
            ->test(QuoteBuilder::class)
            ->set('customer_id', $customer->id)
            ->call('addAdHocLine')
            ->set('lineItems.0.unit_retail_price', '500.00')
            ->set('lineItems.0.unit_trade_price', '300.00')
            ->call('save');

        $this->assertDatabaseHas('quote_line_items', [
            'unit_retail_price' => 500.00,
            'unit_trade_price' => 300.00,
        ]);
    }
}
