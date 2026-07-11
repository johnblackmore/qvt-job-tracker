<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update an existing customer record in the QVT Job Tracker. Requires confirmation.')]
class UpdateCustomerTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The customer ID to update')
                ->required(),
            'name' => $schema->string()
                ->description('Full customer name')
                ->nullable(),
            'email' => $schema->string()
                ->description('Customer email address')
                ->nullable(),
            'phone' => $schema->string()
                ->description('Phone number')
                ->nullable(),
            'address' => $schema->string()
                ->description('Physical address')
                ->nullable(),
            'notes' => $schema->string()
                ->description('Internal notes')
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
            'customer' => $schema->object([
                'id' => $schema->integer(),
                'name' => $schema->string(),
                'email' => $schema->string()->nullable(),
                'phone' => $schema->string()->nullable(),
                'address' => $schema->string()->nullable(),
                'notes' => $schema->string()->nullable(),
                'updated_at' => $schema->string()->nullable(),
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
            'id' => ['required', 'integer', 'exists:customers,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
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

        $customer = Customer::findOrFail($validated['id']);

        $changes = [];
        $fields = ['name', 'email', 'phone', 'address', 'notes'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $changes[$field] = $validated[$field];
            }
        }

        if ($isPreview && ! $isConfirmed) {
            $changeList = '';
            foreach ($changes as $key => $value) {
                $oldValue = $customer->$key ?? '(not set)';
                $changeList .= "\n- {$key}: '{$oldValue}' → '{$value}'";
            }

            return Response::structured([
                'status' => 'preview',
                'message' => "I will update customer {$customer->name}.{$changeList}\n\nIs that correct?",
                'data' => [
                    'id' => $customer->id,
                    ...$changes,
                ],
            ]);
        }

        if (empty($changes)) {
            return Response::structured([
                'status' => 'completed',
                'message' => "No changes were made to customer {$customer->name}.",
                'url' => route('customers.show', $customer),
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'notes' => $customer->notes,
                    'updated_at' => $customer->updated_at->toIso8601String(),
                ],
            ]);
        }

        $customer->update($changes);
        $customer->refresh();

        return Response::structured([
            'status' => 'completed',
            'message' => "I have updated customer {$customer->name}.",
            'url' => route('customers.show', $customer),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'updated_at' => $customer->updated_at->toIso8601String(),
            ],
        ]);
    }
}
