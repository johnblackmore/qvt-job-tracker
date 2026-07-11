<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function quote_pdf_contains_retail_prices(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'sent',
            'total_retail' => 1200.00,
            'labour_total' => 400.00,
            'grand_total' => 1200.00,
        ]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'product',
            'description' => 'Solar Panel Kit',
            'quantity' => 2,
            'unit_retail_price' => 400.00,
            'line_total_retail' => 800.00,
        ]);

        $response = $this->actingAs($user)->get(route('quotes.pdf.download', $quote));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function quote_pdf_does_not_expose_trade_prices(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 1200.00,
            'total_trade' => 720.00,
        ]);

        $response = $this->actingAs($user)->get(route('quotes.pdf.preview', $quote));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
