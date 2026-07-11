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

#[Description('Log a new customer enquiry. Requires confirmation.')]
class CreateEnquiryTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()
                ->description('Optional customer ID to associate the enquiry with')
                ->nullable(),
            'source' => $schema->string()
                ->description('Source of the enquiry: web, phone, email, referral, or other')
                ->enum(['web', 'phone', 'email', 'referral', 'other'])
                ->default('web'),
            'subject' => $schema->string()
                ->description('Optional subject line')
                ->nullable(),
            'message' => $schema->string()
                ->description('The enquiry message body')
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
                'source' => $schema->string(),
                'subject' => $schema->string()->nullable(),
                'message' => $schema->string(),
                'status' => $schema->string(),
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'source' => ['nullable', 'in:web,phone,email,referral,other'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
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

        $customer = null;
        if (! empty($validated['customer_id'])) {
            $customer = Customer::findOrFail($validated['customer_id']);
        }

        $source = $validated['source'] ?? 'web';

        if ($isPreview && ! $isConfirmed) {
            $customerMsg = $customer
                ? "\nCustomer: {$customer->name}"
                : '\nCustomer: (not linked)';

            return Response::structured([
                'status' => 'preview',
                'message' => "I will log a new enquiry.\n\nSource: {$source}{$customerMsg}\nSubject: ".($validated['subject'] ?? '(not set)')."\n\nIs that correct?",
                'data' => [
                    'customer_id' => $customer?->id,
                    'source' => $source,
                    'subject' => $validated['subject'] ?? null,
                    'message' => $validated['message'],
                ],
            ]);
        }

        $enquiry = Enquiry::create([
            'customer_id' => $customer?->id,
            'source' => $source,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'status' => 'new',
            'staff_user_id' => $request->user()?->id,
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => 'I have logged a new enquiry.'.($customer ? " Linked to {$customer->name}." : ''),
            'url' => route('enquiries.edit', $enquiry),
            'enquiry' => [
                'id' => $enquiry->id,
                'customer_id' => $enquiry->customer_id,
                'customer_name' => $customer?->name,
                'source' => $enquiry->source,
                'subject' => $enquiry->subject,
                'message' => $enquiry->message,
                'status' => $enquiry->status,
                'created_at' => $enquiry->created_at->toIso8601String(),
            ],
        ]);
    }
}
