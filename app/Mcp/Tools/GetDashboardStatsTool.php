<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Quote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Retrieve high-level business dashboard statistics: customer counts, quote pipeline, order status, enquiry status, and revenue pipeline.')]
class GetDashboardStatsTool extends Tool
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
            'stats' => $schema->object([]),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $startOfMonth = now()->startOfMonth();

        $customerTotal = Customer::count();
        $customerNewThisMonth = Customer::where('created_at', '>=', $startOfMonth)->count();

        $quoteByStatus = Quote::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $quoteTotalValueAccepted = Quote::where('status', 'accepted')
            ->where('accepted_at', '>=', $startOfMonth)
            ->sum('grand_total');

        $orderByStatus = Order::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $depositCollected = Order::sum('deposit_paid');

        $enquiryByStatus = Enquiry::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $enquiryUnlinked = Enquiry::whereNull('customer_id')->count();

        $revenuePipeline = Quote::whereIn('status', ['draft', 'sent'])
            ->sum('grand_total');

        $stats = [
            'customers' => [
                'total' => $customerTotal,
                'new_this_month' => $customerNewThisMonth,
            ],
            'quotes' => [
                'total' => array_sum($quoteByStatus),
                'by_status' => [
                    'draft' => $quoteByStatus['draft'] ?? 0,
                    'sent' => $quoteByStatus['sent'] ?? 0,
                    'accepted' => $quoteByStatus['accepted'] ?? 0,
                    'declined' => $quoteByStatus['declined'] ?? 0,
                    'expired' => $quoteByStatus['expired'] ?? 0,
                ],
                'total_value_accepted_this_month' => (float) $quoteTotalValueAccepted,
            ],
            'orders' => [
                'total' => array_sum($orderByStatus),
                'by_status' => [
                    'pending' => $orderByStatus['pending'] ?? 0,
                    'deposit_paid' => $orderByStatus['deposit_paid'] ?? 0,
                    'scheduled' => $orderByStatus['scheduled'] ?? 0,
                    'in_progress' => $orderByStatus['in_progress'] ?? 0,
                    'completed' => $orderByStatus['completed'] ?? 0,
                    'cancelled' => $orderByStatus['cancelled'] ?? 0,
                ],
                'deposit_collected' => (float) $depositCollected,
            ],
            'enquiries' => [
                'total' => array_sum($enquiryByStatus),
                'by_status' => [
                    'new' => $enquiryByStatus['new'] ?? 0,
                    'in_progress' => $enquiryByStatus['in_progress'] ?? 0,
                    'responded' => $enquiryByStatus['responded'] ?? 0,
                    'closed' => $enquiryByStatus['closed'] ?? 0,
                ],
                'unlinked' => $enquiryUnlinked,
            ],
            'revenue_pipeline' => [
                'total' => (float) $revenuePipeline,
                'currency' => 'GBP',
            ],
        ];

        return Response::structured([
            'status' => 'completed',
            'message' => 'Dashboard stats for '.now()->format('d F Y').'.',
            'stats' => $stats,
        ]);
    }
}
