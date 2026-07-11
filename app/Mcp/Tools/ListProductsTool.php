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
#[Description('List products from the catalogue with optional filters. Returns paginated products with links to view each record.')]
class ListProductsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()
                ->description('Filter by product category ID.')
                ->nullable(),
            'is_active' => $schema->boolean()
                ->description('Filter by active status.')
                ->nullable(),
            'per_page' => $schema->integer()
                ->description('Items per page (max 100).')
                ->default(20),
            'page' => $schema->integer()
                ->description('Page number.')
                ->default(1),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'data' => $schema->array(),
            'pagination' => $schema->object([]),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:product_categories,id',
            'is_active' => 'nullable|boolean',
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
        ]);

        $query = Product::query();

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $products = $query
            ->with('category')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $products->map(function (Product $product) {
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'retail_price' => $product->retail_price,
                'stock_qty' => $product->stock_qty,
                'is_active' => $product->is_active,
                'category' => $product->category?->name,
                'url' => route('products.show', $product),
            ];
        });

        return Response::structured([
            'status' => 'completed',
            'message' => "Retrieved {$products->count()} products (page {$products->currentPage()} of {$products->lastPage()}).",
            'data' => $data,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
