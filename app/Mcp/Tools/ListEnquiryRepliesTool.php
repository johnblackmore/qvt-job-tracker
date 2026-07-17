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
#[Description('List all replies in an enquiry conversation thread. Returns chronological reply history.')]
class ListEnquiryRepliesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('The enquiry ID to get replies for')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['completed', 'error'])->description('Response status')->required(),
            'message' => $schema->string()->description('Human-readable result message')->required(),
            'replies' => $schema->array($schema->object([
                'id' => $schema->integer(),
                'direction' => $schema->string(),
                'is_internal' => $schema->boolean(),
                'subject' => $schema->string()->nullable(),
                'body' => $schema->string(),
                'staff_name' => $schema->string()->nullable(),
                'status' => $schema->string(),
                'sent_at' => $schema->string()->nullable(),
                'created_at' => $schema->string()->nullable(),
            ])),
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

        $replies = EnquiryReply::with('staff')
            ->where('enquiry_id', $validated['enquiry_id'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($reply) => [
                'id' => $reply->id,
                'direction' => $reply->direction,
                'is_internal' => $reply->is_internal,
                'subject' => $reply->subject,
                'body' => $reply->body,
                'staff_name' => $reply->staff?->name,
                'status' => $reply->status,
                'sent_at' => $reply->sent_at?->toIso8601String(),
                'created_at' => $reply->created_at?->toIso8601String(),
            ]);

        return Response::structured([
            'status' => 'completed',
            'message' => $replies->isEmpty()
                ? 'No replies yet for this enquiry.'
                : 'Found '.$replies->count().' reply'.($replies->count() !== 1 ? 'ies' : 'y').' in the conversation thread.',
            'replies' => $replies->toArray(),
        ]);
    }
}
