<?php

namespace App\Mcp\Tools;

use App\Models\Enquiry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get a single enquiry with full details including customer, replies, quotes, and activity log.')]
class GetEnquiryTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('The enquiry ID to retrieve')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['completed', 'error'])->description('Response status')->required(),
            'message' => $schema->string()->description('Human-readable result message')->required(),
            'url' => $schema->string()->description('Link to view the enquiry in staff admin')->nullable(),
            'enquiry' => $schema->object([
                'id' => $schema->integer(),
                'customer_id' => $schema->integer()->nullable(),
                'customer_name' => $schema->string()->nullable(),
                'email' => $schema->string()->nullable(),
                'phone' => $schema->string()->nullable(),
                'source' => $schema->string(),
                'status' => $schema->string(),
                'subject' => $schema->string()->nullable(),
                'message' => $schema->string(),
                'internal_notes' => $schema->string()->nullable(),
                'created_at' => $schema->string()->nullable(),
                'responded_at' => $schema->string()->nullable(),
                'staff_name' => $schema->string()->nullable(),
                'reply_count' => $schema->integer(),
                'quote_count' => $schema->integer(),
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
        ]);

        $enquiry = Enquiry::with([
            'customer', 'staff', 'replies',
            'quotes', 'activityLogs.staff',
        ])->findOrFail($validated['enquiry_id']);

        return Response::structured([
            'status' => 'completed',
            'message' => 'Found enquiry'.($enquiry->subject ? ': '.$enquiry->subject : '').'.',
            'url' => route('enquiries.show', $enquiry),
            'enquiry' => [
                'id' => $enquiry->id,
                'customer_id' => $enquiry->customer_id,
                'customer_name' => $enquiry->customer?->name,
                'email' => $enquiry->email,
                'phone' => $enquiry->phone,
                'source' => $enquiry->source,
                'status' => $enquiry->status,
                'subject' => $enquiry->subject,
                'message' => $enquiry->message,
                'internal_notes' => $enquiry->internal_notes,
                'created_at' => $enquiry->created_at?->toIso8601String(),
                'responded_at' => $enquiry->responded_at?->toIso8601String(),
                'staff_name' => $enquiry->staff?->name,
                'reply_count' => $enquiry->replies->count(),
                'quote_count' => $enquiry->quotes->count(),
            ],
        ]);
    }
}
