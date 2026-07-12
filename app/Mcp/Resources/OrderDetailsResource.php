<?php

namespace App\Mcp\Resources;

use App\Models\Order;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class OrderDetailsResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('qvt://orders/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = (int) $request->get('id');

        $order = Order::with(['customer', 'quote', 'staff'])
            ->withCount('emailsSent')
            ->findOrFail($id);

        $data = [
            'id' => $order->id,
            'reference_number' => $order->reference_number,
            'status' => $order->status,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'email' => $order->customer->email,
            ] : null,
            'quote' => $order->quote ? [
                'id' => $order->quote->id,
                'reference_number' => $order->quote->reference_number,
                'status' => $order->quote->status,
            ] : null,
            'staff' => $order->staff ? [
                'id' => $order->staff->id,
                'name' => $order->staff->name,
            ] : null,
            'financials' => [
                'total_amount' => (float) $order->total_amount,
                'deposit_required' => (float) $order->deposit_required,
                'deposit_paid' => (float) $order->deposit_paid,
                'balance_due' => (float) $order->balance_due,
                'deposit_percent' => $order->deposit_percent,
            ],
            'scheduled_date' => $order->scheduled_date?->toDateString(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'notes' => $order->notes,
            'emails_sent_count' => $order->emails_sent_count,
            'created_at' => $order->created_at?->toIso8601String(),
            'url' => route('orders.show', $order),
        ];

        return Response::text(json_encode($data, JSON_PRETTY_PRINT));
    }
}
