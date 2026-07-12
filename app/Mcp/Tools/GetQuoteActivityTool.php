<?php

namespace App\Mcp\Tools;

use App\Models\Quote;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Retrieve quote activity in a date range: created, sent, accepted, and declined counts with totals. Includes top 5 recent quotes.')]
class GetQuoteActivityTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()
                ->description('Start date (YYYY-MM-DD). Defaults to 7 days ago.')
                ->nullable(),
            'until' => $schema->string()
                ->description('End date (YYYY-MM-DD). Defaults to today.')
                ->nullable(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'since' => $schema->string(),
            'until' => $schema->string(),
            'activity' => $schema->object([]),
            'recent_quotes' => $schema->array(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date', 'date_format:Y-m-d'],
        ]);

        $since = ! empty($validated['since'])
            ? Carbon::parse($validated['since'])->startOfDay()
            : now()->subDays(7)->startOfDay();

        $until = ! empty($validated['until'])
            ? Carbon::parse($validated['until'])->endOfDay()
            : now()->endOfDay();

        $quotesCreated = Quote::whereBetween('created_at', [$since, $until]);
        $quotesCreatedCount = $quotesCreated->count();
        $quotesCreatedValue = $quotesCreated->sum('grand_total');

        $quotesSentCount = Quote::whereNotNull('sent_at')
            ->whereBetween('sent_at', [$since, $until])
            ->count();
        $quotesSentValue = Quote::whereNotNull('sent_at')
            ->whereBetween('sent_at', [$since, $until])
            ->sum('grand_total');

        $quotesAcceptedCount = Quote::whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$since, $until])
            ->count();
        $quotesAcceptedValue = Quote::whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$since, $until])
            ->sum('grand_total');

        $quotesDeclinedCount = Quote::whereNotNull('declined_at')
            ->whereBetween('declined_at', [$since, $until])
            ->count();

        $recentQuotes = Quote::with('customer')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Quote $quote) {
                return [
                    'id' => $quote->id,
                    'reference_number' => $quote->reference_number,
                    'status' => $quote->status,
                    'grand_total' => (float) $quote->grand_total,
                    'customer_name' => $quote->customer?->name,
                    'url' => route('quotes.show', $quote),
                ];
            });

        $activity = [
            'quotes_created' => [
                'count' => $quotesCreatedCount,
                'total_value' => (float) $quotesCreatedValue,
            ],
            'quotes_sent' => [
                'count' => $quotesSentCount,
                'total_value' => (float) $quotesSentValue,
            ],
            'quotes_accepted' => [
                'count' => $quotesAcceptedCount,
                'total_value' => (float) $quotesAcceptedValue,
            ],
            'quotes_declined' => [
                'count' => $quotesDeclinedCount,
            ],
        ];

        return Response::structured([
            'status' => 'completed',
            'message' => "Quote activity from {$since->format('d F Y')} to {$until->format('d F Y')}.",
            'since' => $since->toDateString(),
            'until' => $until->toDateString(),
            'activity' => $activity,
            'recent_quotes' => $recentQuotes,
        ]);
    }
}
