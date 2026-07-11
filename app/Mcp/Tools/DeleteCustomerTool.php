<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Permanently delete a customer record and all associated data (vehicles, enquiries, quotes, orders). This action is destructive and requires confirmation.')]
class DeleteCustomerTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The customer ID to delete')
                ->required(),
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
            'deleted_id' => $schema->integer()->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:customers,id'],
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

        $customer = Customer::withCount(['vehicles', 'enquiries', 'quotes', 'orders'])
            ->findOrFail($validated['id']);

        if ($isPreview && ! $isConfirmed) {
            $linkedSummary = '';
            if ($customer->vehicles_count > 0) {
                $linkedSummary .= "\n- {$customer->vehicles_count} vehicle(s)";
            }
            if ($customer->enquiries_count > 0) {
                $linkedSummary .= "\n- {$customer->enquiries_count} enquiry/enquiries";
            }
            if ($customer->quotes_count > 0) {
                $linkedSummary .= "\n- {$customer->quotes_count} quote(s)";
            }
            if ($customer->orders_count > 0) {
                $linkedSummary .= "\n- {$customer->orders_count} order(s)";
            }

            return Response::structured([
                'status' => 'preview',
                'message' => "⚠ This will permanently delete customer {$customer->name} and all associated data.{$linkedSummary}\n\nThis action cannot be undone. Are you sure?",
                'data' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'vehicles_count' => $customer->vehicles_count,
                    'enquiries_count' => $customer->enquiries_count,
                    'quotes_count' => $customer->quotes_count,
                    'orders_count' => $customer->orders_count,
                ],
            ]);
        }

        $name = $customer->name;
        $id = $customer->id;
        $customer->delete();

        return Response::structured([
            'status' => 'completed',
            'message' => "Customer {$name} and all associated records have been permanently deleted.",
            'deleted_id' => $id,
        ]);
    }
}
