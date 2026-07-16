<?php

namespace App\Mcp\Tools;

use App\Models\EnquiryReply;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get details of a specific inbound email reply by its ID. Returns full reply details and a link to the staff admin area.')]
class GetInboundReplyTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The inbound reply ID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'reply' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:enquiry_replies,id'],
        ]);

        $reply = EnquiryReply::with('enquiry.customer')
            ->where('direction', 'inbound')
            ->findOrFail($validated['id']);

        return Response::structured([
            'status' => 'completed',
            'message' => 'Retrieved inbound reply #'.$reply->id.' from '.($reply->from_name ?? $reply->from_email ?? 'unknown').'.',
            'reply' => [
                'id' => $reply->id,
                'enquiry_id' => $reply->enquiry_id,
                'enquiry_subject' => $reply->enquiry?->subject,
                'customer_name' => $reply->enquiry?->customer?->name,
                'from_email' => $reply->from_email,
                'from_name' => $reply->from_name,
                'subject' => $reply->subject,
                'body' => $reply->body,
                'status' => $reply->status,
                'postmark_message_id' => $reply->postmark_message_id,
                'received_at' => $reply->sent_at?->toIso8601String(),
                'created_at' => $reply->created_at?->toIso8601String(),
                'url' => route('enquiries.show', $reply->enquiry),
            ],
        ]);
    }
}
