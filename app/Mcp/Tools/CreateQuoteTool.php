<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new blank quote for a customer. Requires confirmation.')]
class CreateQuoteTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()
                ->description('The customer ID to associate the quote with')
                ->required(),
            'notes' => $schema->string()
                ->description('Internal notes for the quote')
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

        $customer = Customer::findOrFail($validated['customer_id']);
        $validUntil = $validated['valid_until'] ?? now()->addDays(30)->format('Y-m-d');
        $reference = 'Q-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will create a new blank quote for {$customer->name}.\n\nReference: {$reference}\nValid until: {$validUntil}\n\nIs that correct?",
                'data' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'reference_number' => $reference,
                    'valid_until' => $validUntil,
                    'notes' => $validated['notes'] ?? null,
                ],
            ]);
        }

        $quote = Quote::create([
            'customer_id' => $customer->id,
            'reference_number' => $reference,
            'status' => 'draft',
            'total_retail' => 0,
            'total_trade' => 0,
            'labour_total' => 0,
            'grand_total' => 0,
            'notes' => $validated['notes'] ?? null,
            'valid_until' => $validUntil,
            'staff_user_id' => $request->user()?->id,
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => "I have created a new quote ({$reference}) for {$customer->name}.",
            'url' => route('quotes.show', $quote),
            'quote' => [
                'id' => $quote->id,
                'reference_number' => $quote->reference_number,
                'customer_id' => $quote->customer_id,
                'customer_name' => $customer->name,
                'status' => $quote->status,
                'grand_total' => $quote->grand_total,
                'valid_until' => $quote->valid_until?->toDateString(),
                'notes' => $quote->notes,
                'created_at' => $quote->created_at->toIso8601String(),
            ],
        ]);
    }
}
