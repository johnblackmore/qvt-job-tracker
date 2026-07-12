<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Order;
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
#[Description('Generate a weekly business summary with narrative message: new customers, new quotes, accepted quotes, new orders, deposit collected, and pending follow-ups.')]
class GetWeeklySummaryTool extends Tool
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
            'week_starting' => $schema->string(),
            'week_ending' => $schema->string(),
            'summary' => $schema->object([]),
            'highlights' => $schema->array(),
            'pending_follow_ups' => $schema->array(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY);

        $newCustomers = Customer::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $newQuotesCount = Quote::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $acceptedQuotesCount = Quote::whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$weekStart, $weekEnd])
            ->count();
        $acceptedQuotesValue = Quote::whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$weekStart, $weekEnd])
            ->sum('grand_total');
        $newOrdersCount = Order::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $depositCollected = Order::whereBetween('created_at', [$weekStart, $weekEnd])
            ->sum('deposit_paid');

        $pendingQuotes = Quote::where('status', 'sent')
            ->where('sent_at', '<=', now()->subDays(3))
            ->with('customer')
            ->orderBy('sent_at')
            ->limit(10)
            ->get()
            ->map(function (Quote $quote) {
                return [
                    'id' => $quote->id,
                    'reference_number' => $quote->reference_number,
                    'customer_name' => $quote->customer?->name,
                    'sent_at' => $quote->sent_at?->toDateString(),
                    'days_waiting' => (int) ($quote->sent_at?->diffInDays(now()) ?? 0),
                    'url' => route('quotes.show', $quote),
                ];
            });

        $topCustomers = Quote::whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$weekStart, $weekEnd])
            ->with('customer')
            ->selectRaw('customer_id, SUM(grand_total) as total_value')
            ->groupBy('customer_id')
            ->orderByDesc('total_value')
            ->limit(3)
            ->get()
            ->map(function ($row) {
                return [
                    'customer_id' => $row->customer_id,
                    'customer_name' => $row->customer?->name,
                    'total_value' => (float) $row->total_value,
                ];
            });

        $narrative = sprintf(
            'This week (Mon %s - %s): %d new customer(s), %d new quote(s), %d accepted at £%s total, £%s deposit collected.',
            $weekStart->format('d F'),
            now()->format('d F Y'),
            $newCustomers,
            $newQuotesCount,
            $acceptedQuotesCount,
            number_format($acceptedQuotesValue, 2),
            number_format($depositCollected, 2)
        );

        if ($pendingQuotes->count() > 0) {
            $narrative .= ' Pending: '.$pendingQuotes->count().' quote(s) awaiting response.';
        }

        return Response::structured([
            'status' => 'completed',
            'message' => $narrative,
            'week_starting' => $weekStart->toDateString(),
            'week_ending' => $weekEnd->toDateString(),
            'summary' => [
                'new_customers' => $newCustomers,
                'new_quotes' => $newQuotesCount,
                'accepted_quotes_count' => $acceptedQuotesCount,
                'accepted_quotes_value' => (float) $acceptedQuotesValue,
                'new_orders' => $newOrdersCount,
                'deposit_collected' => (float) $depositCollected,
            ],
            'highlights' => $topCustomers,
            'pending_follow_ups' => $pendingQuotes,
        ]);
    }
}
