<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search customers by a keyword query matching name, email, or phone. Returns paginated results with links.')]
class SearchCustomersTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search keyword for name, email, or phone.')
                ->required(),
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
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
        ]);

        $search = $validated['query'];
        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $customers = Customer::query()
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->withCount(['vehicles', 'enquiries', 'quotes'])
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $customers->map(function (Customer $customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'vehicles_count' => $customer->vehicles_count,
                'enquiries_count' => $customer->enquiries_count,
                'quotes_count' => $customer->quotes_count,
                'url' => route('customers.show', $customer),
            ];
        });

        return Response::structured([
            'status' => 'completed',
            'message' => "Found {$customers->total()} customers matching '{$search}'.",
            'data' => $data,
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }
}
