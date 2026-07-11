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
#[Description('Search the product catalogue by a keyword query matching name, SKU, or description. Returns paginated products with links.')]
class SearchProductsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search keyword for name, SKU, or description.')
                ->required(),
            'category_id' => $schema->integer()
                ->description('Filter by product category ID.')
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
            'query' => 'required|string|min:1|max:255',
            'category_id' => 'nullable|integer|exists:product_categories,id',
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
        ]);

        $search = $validated['query'];
        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $query = Product::query()
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

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
            'message' => "Found {$products->total()} products matching '{$search}'.",
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
