<?php

namespace App\Mcp\Resources;

use App\Models\SupplierOrder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\ResourceUri;
use Laravel\Mcp\Server\Resource;

#[ResourceUri('qvt://supplier-orders/{id}')]
#[Description('Full details of a single supplier order, including line items, allocations, documents, and bank reconciliation status.')]
class SupplierOrderDetailsResource extends Resource
{
    public function get(Request $request, array $params): ?array
    {
        $id = $params['id'] ?? null;
        if (! $id) {
            return null;
        }

        $order = SupplierOrder::with([
            'supplier', 'lineItems', 'lineItems.allocations',
            'documents', 'bankTransaction', 'createdBy',
        ])->find($id);

        if (! $order) {
            return null;
        }

        return [
            'uri' => "qvt://supplier-orders/{$id}",
            'data' => $order->toArray(),
        ];
    }
}
