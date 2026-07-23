<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Quote;
use App\Services\VatService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Clone an existing quote into a new draft quote. Copies line items, customer, and notes but resets reference, status, and valid_until. Prices are copied verbatim from the original quote. Requires confirmation.')]
class CloneQuoteTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'quote_id' => $schema->integer()
                ->description('The ID of the existing quote to clone')
                ->required(),
            'customer_id' => $schema->integer()
                ->description('Optional override customer ID. Defaults to the source quote\'s customer.')
                ->nullable(),
            'notes' => $schema->string()
                ->description('Override notes for the new quote. If not provided, the source quote\'s notes are used.')
                ->nullable(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without saving.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and execute the action after preview.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view the record in the staff admin area')->nullable(),
            'quote' => $schema->object([
                'id' => $schema->integer(),
                'reference_number' => $schema->string(),
                'customer_id' => $schema->integer(),
                'customer_name' => $schema->string(),
                'status' => $schema->string(),
                'grand_total' => $schema->number()->nullable(),
                'total_retail' => $schema->number()->nullable(),
                'total_trade' => $schema->number()->nullable(),
                'labour_total' => $schema->number()->nullable(),
                'line_items_count' => $schema->integer(),
                'valid_until' => $schema->string()->nullable(),
                'notes' => $schema->string()->nullable(),
                'created_at' => $schema->string()->nullable(),
            ])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error(
                'This action requires confirmation. Set preview=true to review what will happen, or confirmed=true to proceed.'
            );
        }

        $sourceQuote = Quote::with(['customer', 'lineItems'])->findOrFail($validated['quote_id']);
        $customerId = $validated['customer_id'] ?? $sourceQuote->customer_id;
        $customer = Customer::findOrFail($customerId);
        $notes = $validated['notes'] ?? $sourceQuote->notes;

        $reference = 'Q-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        $validUntil = now()->addDays(30)->format('Y-m-d');

        // Build preview items — copy prices verbatim from source
        $previewItems = [];
        $totalRetail = 0;
        $totalTrade = 0;
        $labourTotal = 0;
        $vatService = app(VatService::class);

        foreach ($sourceQuote->lineItems as $item) {
            $qty = $item->quantity;
            $retail = (float) $item->unit_retail_price;
            $trade = (float) $item->unit_trade_price;
            $lineType = $item->line_type;

            $lineRetail = $qty * $retail;
            $lineTrade = $qty * $trade;
            $totalTrade += $lineTrade;

            $vatRateType = 'standard';
            $includesVat = false;
            $vatRate = $vatService->vatRateFor($vatRateType);
            $unitCost = $vatService->calculateTrueCost($trade, $includesVat, $vatRateType);

            if ($lineType === 'labour') {
                $labourTotal += $lineRetail;
            } else {
                $totalRetail += $lineRetail;
            }

            $previewItems[] = [
                'line_type' => $lineType,
                'product_id' => $item->product_id,
                'product_supplier_id' => $item->product_supplier_id,
                'description' => $item->description,
                'quantity' => $qty,
                'unit_retail_price' => $retail,
                'unit_trade_price' => $trade,
                'vat_rate' => $vatRate,
                'unit_cost_price' => $unitCost,
                'line_total_retail' => $lineRetail,
                'line_total_trade' => $lineTrade,
                'line_total_cost' => $qty * $unitCost,
                'notes' => $item->notes,
            ];
        }

        $grandTotal = $totalRetail + $labourTotal;
        $lineCount = count($previewItems);

        $customerLabel = $customerId === $sourceQuote->customer_id
            ? $customer->name
            : $customer->name.' (overridden from '.$sourceQuote->customer?->name.')';

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will clone the quote {$sourceQuote->reference_number} for {$customerLabel}.\n\n{$lineCount} line item(s) will be copied with their original prices.\nGrand total: £".number_format($grandTotal, 2)."\nThe new quote will be created as a draft with a new reference.\n\nIs that correct?",
                'data' => [
                    'source_quote_id' => $sourceQuote->id,
                    'source_reference' => $sourceQuote->reference_number,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_overridden' => $customerId !== $sourceQuote->customer_id,
                    'reference_number' => $reference,
                    'valid_until' => $validUntil,
                    'notes' => $notes,
                    'line_items' => $previewItems,
                    'grand_total' => $grandTotal,
                ],
            ]);
        }

        $quote = Quote::create([
            'customer_id' => $customer->id,
            'reference_number' => $reference,
            'status' => 'draft',
            'total_retail' => $totalRetail,
            'total_trade' => $totalTrade,
            'total_cost' => collect($previewItems)->sum('line_total_cost'),
            'labour_total' => $labourTotal,
            'grand_total' => $grandTotal,
            'notes' => $notes,
            'valid_until' => $validUntil,
            'enquiry_id' => $sourceQuote->enquiry_id,
            'staff_user_id' => $request->user()?->id,
        ]);

        foreach ($previewItems as $item) {
            $quote->lineItems()->create([
                'line_type' => $item['line_type'],
                'product_id' => $item['product_id'],
                'product_supplier_id' => $item['product_supplier_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_retail_price' => $item['unit_retail_price'],
                'unit_trade_price' => $item['unit_trade_price'],
                'vat_rate' => $item['vat_rate'],
                'unit_cost_price' => $item['unit_cost_price'],
                'line_total_retail' => $item['line_total_retail'],
                'line_total_trade' => $item['line_total_trade'],
                'line_total_cost' => $item['line_total_cost'],
                'notes' => $item['notes'],
            ]);
        }

        return Response::structured([
            'status' => 'completed',
            'message' => "I have created a new quote ({$reference}) for {$customer->name}, cloned from {$sourceQuote->reference_number}. Grand total: £".number_format($grandTotal, 2),
            'url' => route('quotes.show', $quote),
            'quote' => [
                'id' => $quote->id,
                'reference_number' => $quote->reference_number,
                'customer_id' => $quote->customer_id,
                'customer_name' => $customer->name,
                'status' => $quote->status,
                'grand_total' => $quote->grand_total,
                'total_retail' => $quote->total_retail,
                'total_trade' => $quote->total_trade,
                'labour_total' => $quote->labour_total,
                'line_items_count' => $lineCount,
                'valid_until' => $quote->valid_until?->toDateString(),
                'notes' => $quote->notes,
                'created_at' => $quote->created_at->toIso8601String(),
            ],
        ]);
    }
}
