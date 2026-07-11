<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SampleQuote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new quote by cloning a sample/template quote for a specific customer. Pulls current retail and trade prices. Requires confirmation.')]
class CreateQuoteFromTemplateTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'sample_quote_id' => $schema->integer()
                ->description('The sample quote/template ID to clone from')
                ->required(),
            'customer_id' => $schema->integer()
                ->description('The customer ID to associate the new quote with')
                ->required(),
            'notes' => $schema->string()
                ->description('Internal notes for the new quote')
                ->nullable(),
            'valid_until' => $schema->string()
                ->description('Quote expiry date (YYYY-MM-DD). Defaults to 30 days from today.')
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
            'sample_quote_id' => ['required', 'integer', 'exists:sample_quotes,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'valid_until' => ['nullable', 'date', 'date_format:Y-m-d'],
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

        $sampleQuote = SampleQuote::findOrFail($validated['sample_quote_id']);
        $customer = Customer::findOrFail($validated['customer_id']);
        $validUntil = $validated['valid_until'] ?? now()->addDays(30)->format('Y-m-d');
        $reference = 'Q-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));

        $templateItems = $sampleQuote->line_items ?? [];

        // Build preview of line items with current prices
        $previewItems = [];
        $previewRetail = 0;
        $previewTrade = 0;
        $previewLabour = 0;

        foreach ($templateItems as $item) {
            $product = null;
            $retail = (float) ($item['unit_retail_price'] ?? 0);
            $trade = (float) ($item['unit_trade_price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);
            $lineType = $item['line_type'] ?? 'product';
            $description = $item['description'] ?? '';

            if (! empty($item['product_id'])) {
                $product = Product::with('suppliers')->find($item['product_id']);
                if ($product) {
                    $retail = (float) $product->retail_price;
                    $preferredSupplier = $product->preferredSupplier();
                    $trade = $preferredSupplier ? (float) $preferredSupplier->pivot->trade_price : 0;
                    if (empty($description)) {
                        $description = $product->name;
                    }
                }
            }

            $lineRetail = $qty * $retail;
            $lineTrade = $qty * $trade;
            $previewRetail += $lineRetail;
            $previewTrade += $lineTrade;

            if ($lineType === 'labour') {
                $previewLabour += $lineRetail;
            }

            $previewItems[] = [
                'line_type' => $lineType,
                'description' => $description,
                'quantity' => $qty,
                'unit_retail_price' => $retail,
                'unit_trade_price' => $trade,
                'line_total_retail' => $lineRetail,
                'line_total_trade' => $lineTrade,
            ];
        }

        $previewGrand = $previewRetail;
        $lineCount = count($previewItems);

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will create a new quote for {$customer->name} based on the '{$sampleQuote->name}' sample quote.\n\n{$lineCount} line item(s) will be added with current retail prices.\n\nGrand total: £".number_format($previewGrand, 2)."\n\nIs that correct?",
                'data' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'sample_quote_id' => $sampleQuote->id,
                    'sample_quote_name' => $sampleQuote->name,
                    'reference_number' => $reference,
                    'valid_until' => $validUntil,
                    'notes' => $validated['notes'] ?? null,
                    'line_items' => $previewItems,
                    'grand_total' => $previewGrand,
                ],
            ]);
        }

        $quote = Quote::create([
            'customer_id' => $customer->id,
            'reference_number' => $reference,
            'status' => 'draft',
            'total_retail' => $previewRetail,
            'total_trade' => $previewTrade,
            'labour_total' => $previewLabour,
            'grand_total' => $previewGrand,
            'notes' => $validated['notes'] ?? null,
            'valid_until' => $validUntil,
            'staff_user_id' => $request->user()?->id,
        ]);

        foreach ($previewItems as $item) {
            $quote->lineItems()->create([
                'line_type' => $item['line_type'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_retail_price' => $item['unit_retail_price'],
                'unit_trade_price' => $item['unit_trade_price'],
                'line_total_retail' => $item['line_total_retail'],
                'line_total_trade' => $item['line_total_trade'],
            ]);
        }

        return Response::structured([
            'status' => 'completed',
            'message' => "I have created a new quote ({$reference}) for {$customer->name} from the '{$sampleQuote->name}' template.",
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
