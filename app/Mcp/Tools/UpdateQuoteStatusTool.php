<?php

namespace App\Mcp\Tools;

use App\Models\Quote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update the status of a quote (draft, sent, accepted, declined, expired). Stamps the appropriate timestamp automatically. Requires confirmation.')]
class UpdateQuoteStatusTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The quote ID to update')
                ->required(),
            'status' => $schema->string()
                ->description('New status: draft, sent, accepted, declined, or expired')
                ->enum(['draft', 'sent', 'accepted', 'declined', 'expired'])
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
            'quote' => $schema->object([
                'id' => $schema->integer(),
                'reference_number' => $schema->string(),
                'status' => $schema->string(),
                'sent_at' => $schema->string()->nullable(),
                'accepted_at' => $schema->string()->nullable(),
                'declined_at' => $schema->string()->nullable(),
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
            'id' => ['required', 'integer', 'exists:quotes,id'],
            'status' => ['required', 'in:draft,sent,accepted,declined,expired'],
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

        $quote = Quote::findOrFail($validated['id']);
        $newStatus = $validated['status'];
        $oldStatus = $quote->status;

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will update quote {$quote->reference_number} status from '{$oldStatus}' to '{$newStatus}'.\n\nIs that correct?",
                'data' => [
                    'id' => $quote->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
            ]);
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'sent' && $quote->sent_at === null) {
            $updateData['sent_at'] = now();
        } elseif ($newStatus === 'accepted' && $quote->accepted_at === null) {
            $updateData['accepted_at'] = now();
        } elseif ($newStatus === 'declined' && $quote->declined_at === null) {
            $updateData['declined_at'] = now();
        }

        $quote->update($updateData);
        $quote->refresh();

        return Response::structured([
            'status' => 'completed',
            'message' => "Quote {$quote->reference_number} status updated from '{$oldStatus}' to '{$newStatus}'.",
            'url' => route('quotes.show', $quote),
            'quote' => [
                'id' => $quote->id,
                'reference_number' => $quote->reference_number,
                'status' => $quote->status,
                'sent_at' => $quote->sent_at?->toIso8601String(),
                'accepted_at' => $quote->accepted_at?->toIso8601String(),
                'declined_at' => $quote->declined_at?->toIso8601String(),
                'updated_at' => $quote->updated_at->toIso8601String(),
            ],
        ]);
    }
}
