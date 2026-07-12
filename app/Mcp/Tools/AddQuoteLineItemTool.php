<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use App\Models\Quote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Add a line item (product, labour, or ad-hoc) to an existing draft quote. Recalculates totals automatically. Requires confirmation.')]
class AddQuoteLineItemTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'quote_id' => $schema->integer()
                ->description('The quote ID to add the line item to')
                ->required(),
            'line_type' => $schema->string()
                ->description('Type of line: product, labour, or ad_hoc')
                ->enum(['product', 'labour', 'ad_hoc'])
                ->required(),
            'product_id' => $schema->integer()
                ->description('The product ID (required for product lines, optional otherwise)')
                ->nullable(),
            'quantity' => $schema->integer()
                ->description('Quantity (default 1)')
                ->default(1),
            'description' => $schema->string()
                ->description('Line description. Auto-populated from product name if product_id given and not provided.')
                ->nullable(),
            'unit_retail_price' => $schema->number()
                ->description('Retail price per unit. Auto-populated from product if product_id given and not provided.')
                ->nullable(),
            'unit_trade_price' => $schema->number()
                ->description('Trade price per unit. Auto-populated from preferred supplier if product_id given and not provided.')
                ->nullable(),
            'notes' => $schema->string()
                ->description('Internal notes for this line item')
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
                'grand_total' => $schema->number()->nullable(),
                'total_retail' => $schema->number()->nullable(),
                'labour_total' => $schema->number()->nullable(),
                'line_items_count' => $schema->integer(),
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
            'line_type' => ['required', 'in:product,labour,ad_hoc'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit_retail_price' => ['nullable', 'numeric', 'min:0'],
            'unit_trade_price' => ['nullable', 'numeric', 'min:0'],
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

        $quote = Quote::findOrFail($validated['quote_id']);
        $qty = (int) ($validated['quantity'] ?? 1);
        $lineType = $validated['line_type'];
        $productId = $validated['product_id'] ?? null;

        $product = null;
        if ($productId) {
            $product = Product::with('suppliers')->find($productId);
        }

        $retail = 0;
        $trade = 0;
        $description = $validated['description'] ?? '';

        if ($product) {
            $retail = (float) ($validated['unit_retail_price'] ?? $product->retail_price ?? 0);
            $preferredSupplier = $product->preferredSupplier();
            $trade = (float) ($validated['unit_trade_price'] ?? ($preferredSupplier ? $preferredSupplier->pivot->trade_price : 0));
            if (empty($description)) {
                $description = $product->name;
            }
        } else {
            $retail = (float) ($validated['unit_retail_price'] ?? 0);
            $trade = (float) ($validated['unit_trade_price'] ?? 0);
        }

        if ($lineType === 'labour' && empty($description)) {
            $description = 'Labour';
        }

        $lineRetail = $qty * $retail;
        $lineTrade = $qty * $trade;

        $newTotalRetail = (float) $quote->total_retail + $lineRetail;
        $newTotalTrade = (float) $quote->total_trade + $lineTrade;
        $newLabour = (float) $quote->labour_total + ($lineType === 'labour' ? $lineRetail : 0);
        $newGrand = $newTotalRetail;
        $newLineCount = $quote->lineItems()->count() + 1;

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will add a {$lineType} line to quote {$quote->reference_number}.\n\nDescription: {$description}\nQuantity: {$qty} × £".number_format($retail, 2).' = £'.number_format($lineRetail, 2)."\n\nNew quote total: £".number_format($newGrand, 2)."\n\nIs that correct?",
                'data' => [
                    'quote_id' => $quote->id,
                    'line_type' => $lineType,
                    'description' => $description,
                    'quantity' => $qty,
                    'unit_retail_price' => $retail,
                    'unit_trade_price' => $trade,
                    'line_total_retail' => $lineRetail,
                    'line_total_trade' => $lineTrade,
                    'new_grand_total' => $newGrand,
                ],
            ]);
        }

        $quote->lineItems()->create([
            'line_type' => $lineType,
            'product_id' => $productId,
            'description' => $description,
            'quantity' => $qty,
            'unit_retail_price' => $retail,
            'unit_trade_price' => $trade,
            'line_total_retail' => $lineRetail,
            'line_total_trade' => $lineTrade,
            'notes' => $validated['notes'] ?? null,
        ]);

        $quote->update([
            'total_retail' => $newTotalRetail,
            'total_trade' => $newTotalTrade,
            'labour_total' => $newLabour,
            'grand_total' => $newGrand,
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => "I have added a {$lineType} line to quote {$quote->reference_number}.",
            'url' => route('quotes.show', $quote),
            'quote' => [
                'id' => $quote->id,
                'reference_number' => $quote->reference_number,
                'grand_total' => $newGrand,
                'total_retail' => $newTotalRetail,
                'labour_total' => $newLabour,
                'line_items_count' => $newLineCount,
            ],
        ]);
    }
}
