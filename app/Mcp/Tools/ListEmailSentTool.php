<?php

namespace App\Mcp\Tools;

use App\Models\EmailSent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List sent emails with optional filtering by status, customer, and date range. Returns paginated sent emails with delivery tracking data.')]
class ListEmailSentTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->description('Filter by email status (pending, sent, failed)')
                ->nullable(),
            'customer_id' => $schema->integer()
                ->description('Filter by customer ID')
                ->nullable(),
            'since' => $schema->string()
                ->description('Filter emails sent on or after this date (YYYY-MM-DD)')
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
            'status' => ['nullable', 'in:pending,sent,failed'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'since' => ['nullable', 'date', 'date_format:Y-m-d'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'page' => ['integer', 'min:1'],
        ]);

        $query = EmailSent::query()->with('customer');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['customer_id'])) {
            $query->where('customer_id', $validated['customer_id']);
        }

        if (! empty($validated['since'])) {
            $query->whereDate('created_at', '>=', $validated['since']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $emails = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $emails->map(function (EmailSent $email) {
            $timestamps = [];
            foreach (['delivered_at', 'opened_at', 'clicked_at', 'bounced_at', 'spam_complaint_at'] as $field) {
                if ($email->$field) {
                    $timestamps[$field] = $email->$field?->toIso8601String();
                }
            }

            return [
                'id' => $email->id,
                'to_email' => $email->to_email,
                'subject' => $email->subject,
                'status' => $email->status,
                'customer_id' => $email->customer_id,
                'customer_name' => $email->customer?->name,
                'bounce_type' => $email->bounce_type,
                'error_message' => Str::limit($email->error_message, 200),
                'timestamps' => $timestamps,
                'sent_at' => $email->sent_at?->toIso8601String(),
                'created_at' => $email->created_at?->toIso8601String(),
            ];
        });

        return Response::structured([
            'status' => 'completed',
            'message' => 'Retrieved '.$emails->count().' sent emails (page '.$emails->currentPage().' of '.$emails->lastPage().').',
            'data' => $data,
            'pagination' => [
                'current_page' => $emails->currentPage(),
                'last_page' => $emails->lastPage(),
                'per_page' => $emails->perPage(),
                'total' => $emails->total(),
            ],
        ]);
    }
}
