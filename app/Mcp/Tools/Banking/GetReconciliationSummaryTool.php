<?php

namespace App\Mcp\Tools\Banking;

use App\Banking\Services\ReconciliationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get a summary of reconciliation status: matched/unmatched/ignored counts, match rate, and unlinked payments.')]
class GetReconciliationSummaryTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'summary' => $schema->object([]),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $service = app(ReconciliationService::class);
        $summary = $service->getSummary();

        $message = 'Reconciliation summary: '
            .$summary['matched_transactions'].' matched, '
            .$summary['unmatched_transactions'].' unmatched, '
            .$summary['ignored_transactions'].' ignored '
            .'('.$summary['match_rate'].'% match rate). '
            .$summary['unlinked_payments'].' unlinked order payment'.($summary['unlinked_payments'] !== 1 ? 's' : '').'.';

        return Response::structured([
            'status' => 'completed',
            'message' => $message,
            'summary' => $summary,
        ]);
    }
}
