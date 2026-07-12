<?php

namespace App\Mcp\Resources;

use App\Models\Quote;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class QuoteDetailsResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('qvt://quotes/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = (int) $request->get('id');

        $quote = Quote::with(['customer', 'lineItems', 'staff'])
            ->findOrFail($id);

        $data = [
            'id' => $quote->id,
            'reference_number' => $quote->reference_number,
            'status' => $quote->status,
            'customer' => $quote->customer ? [
                'id' => $quote->customer->id,
                'name' => $quote->customer->name,
                'email' => $quote->customer->email,
            ] : null,
            'staff' => $quote->staff ? [
                'id' => $quote->staff->id,
                'name' => $quote->staff->name,
            ] : null,
            'totals' => [
                'grand_total' => (float) $quote->grand_total,
                'labour_total' => (float) $quote->labour_total,
                'total_retail' => (float) $quote->total_retail,
            ],
            'line_items' => $quote->lineItems->map(fn ($item) => [
                'id' => $item->id,
                'line_type' => $item->line_type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_retail_price' => (float) $item->unit_retail_price,
                'line_total_retail' => (float) $item->line_total_retail,
            ]),
            'valid_until' => $quote->valid_until?->toDateString(),
            'notes' => $quote->notes,
            'sent_at' => $quote->sent_at?->toIso8601String(),
            'accepted_at' => $quote->accepted_at?->toIso8601String(),
            'declined_at' => $quote->declined_at?->toIso8601String(),
            'created_at' => $quote->created_at?->toIso8601String(),
            'url' => route('quotes.show', $quote),
        ];

        return Response::text(json_encode($data, JSON_PRETTY_PRINT));
    }
}
