<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Enquiry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Link an existing enquiry to a customer record. Allows overwrite of an existing link. Requires confirmation.')]
class LinkEnquiryToCustomerTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('The enquiry ID to link')
                ->required(),
            'customer_id' => $schema->integer()
                ->description('The customer ID to link the enquiry to')
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
            'enquiry' => $schema->object([
                'id' => $schema->integer(),
                'customer_id' => $schema->integer()->nullable(),
                'customer_name' => $schema->string()->nullable(),
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
            'enquiry_id' => ['required', 'integer', 'exists:enquiries,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
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

        $enquiry = Enquiry::findOrFail($validated['enquiry_id']);
        $customer = Customer::findOrFail($validated['customer_id']);
        $oldCustomerName = $enquiry->customer?->name ?? '(not linked)';

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will link enquiry to {$customer->name}.\n\nEnquiry: ".($enquiry->subject ?? '(no subject)')."\nCustomer: {$oldCustomerName} → {$customer->name}\n\nIs that correct?",
                'data' => [
                    'enquiry_id' => $enquiry->id,
                    'old_customer' => $oldCustomerName,
                    'new_customer' => $customer->name,
                ],
            ]);
        }

        $enquiry->update(['customer_id' => $customer->id]);
        $enquiry->refresh();

        return Response::structured([
            'status' => 'completed',
            'message' => "Enquiry linked to {$customer->name}.",
            'url' => route('enquiries.edit', $enquiry),
            'enquiry' => [
                'id' => $enquiry->id,
                'customer_id' => $enquiry->customer_id,
                'customer_name' => $customer->name,
                'updated_at' => $enquiry->updated_at->toIso8601String(),
            ],
        ]);
    }
}
