<?php

namespace App\Mcp\Tools;

use App\Models\Enquiry;
use App\Models\EnquiryActivityLog;
use App\Models\Quote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Create a new quote linked to an enquiry. The quote will be linked to the enquiry for tracking. Requires confirmation.')]
class CreateQuoteFromEnquiryTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('The enquiry ID to create a quote from')
                ->required(),
            'status' => $schema->string()
                ->description('Initial quote status')
                ->enum(['draft', 'sent'])
                ->default('draft'),
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
            'url' => $schema->string()->description('Link to edit the quote in staff admin')->nullable(),
            'quote' => $schema->object([
                'id' => $schema->integer(),
                'reference_number' => $schema->string(),
                'status' => $schema->string(),
                'customer_name' => $schema->string()->nullable(),
                'grand_total' => $schema->string(),
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
            'status' => ['nullable', 'in:draft,sent'],
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

        $enquiry = Enquiry::with('customer')->findOrFail($validated['enquiry_id']);

        if ($isPreview && ! $isConfirmed) {
            $customerName = $enquiry->customer?->name ?? '(no linked customer)';

            return Response::structured([
                'status' => 'preview',
                'message' => "I will create a new quote linked to this enquiry.\n\nCustomer: {$customerName}\nEnquiry: {$enquiry->subject}\n\nIs that correct?",
                'data' => [
                    'enquiry_id' => $enquiry->id,
                    'customer_id' => $enquiry->customer_id,
                    'customer_name' => $enquiry->customer?->name,
                    'enquiry_subject' => $enquiry->subject,
                ],
            ]);
        }

        if (! $enquiry->customer_id) {
            return Response::error(
                'Cannot create a quote for this enquiry because it has no linked customer. Link the enquiry to a customer first.'
            );
        }

        $referenceNumber = 'Q-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));

        $quote = Quote::create([
            'customer_id' => $enquiry->customer_id,
            'enquiry_id' => $enquiry->id,
            'reference_number' => $referenceNumber,
            'status' => $validated['status'] ?? 'draft',
            'total_retail' => 0,
            'total_trade' => 0,
            'labour_total' => 0,
            'grand_total' => 0,
            'staff_user_id' => $request->user()?->id,
        ]);

        EnquiryActivityLog::create([
            'enquiry_id' => $enquiry->id,
            'staff_user_id' => $request->user()?->id,
            'action' => 'quote_created',
            'description' => 'Quote created: '.$referenceNumber,
            'metadata' => ['quote_id' => $quote->id, 'quote_reference' => $referenceNumber],
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => 'Quote '.$referenceNumber.' created and linked to the enquiry. Add line items using the Add Quote Line Item tool.',
            'url' => route('quotes.edit', $quote),
            'quote' => [
                'id' => $quote->id,
                'reference_number' => $quote->reference_number,
                'status' => $quote->status,
                'customer_name' => $enquiry->customer?->name,
                'grand_total' => number_format($quote->grand_total, 2),
            ],
        ]);
    }
}
