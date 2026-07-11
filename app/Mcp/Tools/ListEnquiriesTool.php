<?php

namespace App\Mcp\Tools;

use App\Models\Enquiry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List enquiries with optional filtering by status, source, and date range. Returns paginated enquiries with links.')]
class ListEnquiriesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->description('Filter by enquiry status (new, in_progress, responded, closed)')
                ->nullable(),
            'source' => $schema->string()
                ->description('Filter by source (web, phone, email, referral, other)')
                ->nullable(),
            'since' => $schema->string()
                ->description('Filter enquiries created on or after this date (YYYY-MM-DD)')
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
            'status' => ['nullable', 'in:new,in_progress,responded,closed'],
            'source' => ['nullable', 'in:web,phone,email,referral,other'],
            'since' => ['nullable', 'date', 'date_format:Y-m-d'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'page' => ['integer', 'min:1'],
        ]);

        $query = Enquiry::query()->with('customer');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['source'])) {
            $query->where('source', $validated['source']);
        }

        if (! empty($validated['since'])) {
            $query->whereDate('created_at', '>=', $validated['since']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $enquiries = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $enquiries->map(function (Enquiry $enquiry) {
            return [
                'id' => $enquiry->id,
                'subject' => $enquiry->subject,
                'status' => $enquiry->status,
                'source' => $enquiry->source,
                'customer_id' => $enquiry->customer_id,
                'customer_name' => $enquiry->customer?->name,
                'message_preview' => Str::limit($enquiry->message, 100),
                'created_at' => $enquiry->created_at?->toIso8601String(),
                'responded_at' => $enquiry->responded_at?->toIso8601String(),
                'url' => route('enquiries.edit', $enquiry),
            ];
        });

        return Response::structured([
            'status' => 'completed',
            'message' => "Retrieved {$enquiries->count()} enquiries (page {$enquiries->currentPage()} of {$enquiries->lastPage()}).",
            'data' => $data,
            'pagination' => [
                'current_page' => $enquiries->currentPage(),
                'last_page' => $enquiries->lastPage(),
                'per_page' => $enquiries->perPage(),
                'total' => $enquiries->total(),
            ],
        ]);
    }
}
