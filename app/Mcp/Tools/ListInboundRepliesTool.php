<?php

namespace App\Mcp\Tools;

use App\Models\EnquiryReply;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List inbound email replies with optional filtering. Returns paginated inbound replies with links to the staff admin area.')]
class ListInboundRepliesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'enquiry_id' => $schema->integer()
                ->description('Filter by enquiry ID')
                ->nullable(),
            'since' => $schema->string()
                ->description('Filter replies received on or after this date (YYYY-MM-DD)')
                ->nullable(),
            'per_page' => $schema->integer()
                ->description('Items per page (max 100)')
                ->default(20),
            'page' => $schema->integer()
                ->description('Page number')
                ->default(1),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
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
            'enquiry_id' => ['nullable', 'integer', 'exists:enquiries,id'],
            'since' => ['nullable', 'date', 'date_format:Y-m-d'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'page' => ['integer', 'min:1'],
        ]);

        $query = EnquiryReply::with('enquiry')->where('direction', 'inbound');

        if (! empty($validated['enquiry_id'])) {
            $query->where('enquiry_id', $validated['enquiry_id']);
        }

        if (! empty($validated['since'])) {
            $query->whereDate('created_at', '>=', $validated['since']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $replies = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $replies->map(function (EnquiryReply $reply) {
            return [
                'id' => $reply->id,
                'enquiry_id' => $reply->enquiry_id,
                'enquiry_subject' => $reply->enquiry?->subject,
                'from_email' => $reply->from_email,
                'from_name' => $reply->from_name,
                'subject' => $reply->subject,
                'body_preview' => Str::limit($reply->body, 150),
                'status' => $reply->status,
                'received_at' => $reply->sent_at?->toIso8601String(),
                'created_at' => $reply->created_at?->toIso8601String(),
                'url' => $reply->enquiry ? route('enquiries.show', $reply->enquiry) : null,
            ];
        });

        return Response::structured([
            'status' => 'completed',
            'message' => 'Retrieved '.$replies->count().' inbound replies (page '.$replies->currentPage().' of '.$replies->lastPage().').',
            'data' => $data,
            'pagination' => [
                'current_page' => $replies->currentPage(),
                'last_page' => $replies->lastPage(),
                'per_page' => $replies->perPage(),
                'total' => $replies->total(),
            ],
        ]);
    }
}
