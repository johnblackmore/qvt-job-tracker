<?php

namespace Tests\Feature;

use App\Livewire\Quotes\QuoteBuilder;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteCloneTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function clone_quote_loads_builder_with_copied_data(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'notes' => 'Original notes',
        ]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'product',
            'description' => 'Test Product',
            'quantity' => 2,
            'unit_retail_price' => 100.00,
            'unit_trade_price' => 60.00,
            'line_total_retail' => 200.00,
            'line_total_trade' => 120.00,
        ]);

        Livewire::actingAs($user)
            ->test(QuoteBuilder::class, ['sourceQuoteId' => $quote->id])
            ->assertSet('customer_id', $customer->id)
            ->assertSet('notes', 'Original notes')
            ->assertSet('reference_number', '')
            ->assertSet('status', 'draft')
            ->assertSet('sourceQuoteId', $quote->id);
    }

    #[Test]
    public function clone_quote_copies_line_items(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'product',
            'description' => 'Copied Product',
            'quantity' => 3,
            'unit_retail_price' => 150.00,
            'unit_trade_price' => 90.00,
        ]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'labour',
            'description' => 'Copied Labour',
            'quantity' => 1,
            'unit_retail_price' => 200.00,
            'unit_trade_price' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(QuoteBuilder::class, ['sourceQuoteId' => $quote->id])
            ->assertSet('lineItems.0.description', 'Copied Product')
            ->assertSet('lineItems.0.quantity', '3')
            ->assertSet('lineItems.0.unit_retail_price', '150.00')
            ->assertSet('lineItems.0.unit_trade_price', '90.00')
            ->assertSet('lineItems.1.description', 'Copied Labour')
            ->assertSet('lineItems.1.unit_retail_price', '200.00')
            ->assertCount('lineItems', 2);
    }

    #[Test]
    public function clone_quote_saves_as_new_quote(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-ORIGINAL-001',
            'notes' => 'Clone me',
        ]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'ad_hoc',
            'description' => 'Item to clone',
            'quantity' => 1,
            'unit_retail_price' => 75.00,
            'unit_trade_price' => 45.00,
            'line_total_retail' => 75.00,
            'line_total_trade' => 45.00,
        ]);

        $originalQuoteCount = Quote::count();

        Livewire::actingAs($user)
            ->test(QuoteBuilder::class, ['sourceQuoteId' => $quote->id])
            ->call('save')
            ->assertRedirect(route('quotes.index'));

        // A new quote was created (not updated)
        $this->assertEquals($originalQuoteCount + 1, Quote::count());

        // Original quote still exists
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'reference_number' => 'Q-ORIGINAL-001',
        ]);

        // New quote has a different reference, same customer, copied notes
        $newQuote = Quote::where('id', '!=', $quote->id)->latest()->first();
        $this->assertNotNull($newQuote);
        $this->assertNotEquals($newQuote->reference_number, $quote->reference_number);
        $this->assertEquals($customer->id, $newQuote->customer_id);
        $this->assertEquals('Clone me', $newQuote->notes);
        $this->assertEquals('draft', $newQuote->status);
    }

    #[Test]
    public function clone_quote_resets_valid_until(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'valid_until' => now()->addDays(60),
        ]);

        $expectedDefault = now()->addDays(30)->format('Y-m-d');

        Livewire::actingAs($user)
            ->test(QuoteBuilder::class, ['sourceQuoteId' => $quote->id])
            ->assertSet('valid_until', $expectedDefault);
    }

    #[Test]
    public function clone_quote_preserves_enquiry_link(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $enquiry = Enquiry::factory()->create(['customer_id' => $customer->id]);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'enquiry_id' => $enquiry->id,
        ]);

        Livewire::actingAs($user)
            ->test(QuoteBuilder::class, ['sourceQuoteId' => $quote->id])
            ->assertSet('enquiryId', $enquiry->id);
    }

    #[Test]
    public function clone_quote_route_is_accessible(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($user)
            ->get(route('quotes.create-from-existing', $quote->id))
            ->assertOk();
    }

    #[Test]
    public function clone_quote_from_existing_requires_auth(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $this->get(route('quotes.create-from-existing', $quote->id))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function clone_quote_preserves_staff_user_id(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $staffUser = User::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'staff_user_id' => $staffUser->id,
        ]);

        Livewire::actingAs($user)
            ->test(QuoteBuilder::class, ['sourceQuoteId' => $quote->id])
            ->call('save');

        $newQuote = Quote::where('id', '!=', $quote->id)->latest()->first();
        $this->assertEquals($user->id, $newQuote->staff_user_id);
    }

    #[Test]
    public function clone_quote_404_with_invalid_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('quotes.create-from-existing', 99999))
            ->assertNotFound();
    }
}
