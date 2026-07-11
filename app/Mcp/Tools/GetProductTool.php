<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Fetch a single product record by ID, including its category and supplier details with internal trade prices.')]
class GetProductTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The product ID.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'url' => $schema->string(),
            'product' => $schema->object([]),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:products,id',
        ]);

        $product = Product::with(['category', 'suppliers'])
            ->findOrFail($validated['id']);

        return Response::structured([
            'status' => 'completed',
            'message' => "Retrieved product {$product->name}.",
            'url' => route('products.show', $product),
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'retail_price' => $product->retail_price,
                'stock_qty' => $product->stock_qty,
                'is_active' => $product->is_active,
                'notes' => $product->notes,
                'category' => $product->category?->name,
                'suppliers' => $product->suppliers->map(function ($supplier) {
                    return [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'internal_trade_price' => $supplier->pivot->trade_price,
                        'supplier_sku' => $supplier->pivot->supplier_sku,
                        'is_preferred' => $supplier->pivot->is_preferred,
                        'lead_time_days' => $supplier->pivot->lead_time_days,
                        'supplier_product_url' => $supplier->pivot->supplier_product_url,
                    ];
                }),
            ],
        ]);
    }
}
