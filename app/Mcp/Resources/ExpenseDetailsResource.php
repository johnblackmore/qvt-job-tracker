<?php

namespace App\Mcp\Resources;

use App\Models\Expense;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\ResourceUri;
use Laravel\Mcp\Server\Resource;

#[ResourceUri('qvt://expenses/{id}')]
#[Description('Full details of a single business expense, including line items, documents, and bank reconciliation status.')]
class ExpenseDetailsResource extends Resource
{
    public function get(Request $request, array $params): ?array
    {
        $id = $params['id'] ?? null;
        if (! $id) {
            return null;
        }

        $expense = Expense::with([
            'category', 'lineItems', 'documents', 'bankTransaction', 'createdBy',
        ])->find($id);

        if (! $expense) {
            return null;
        }

        return [
            'uri' => "qvt://expenses/{$id}",
            'data' => $expense->toArray(),
        ];
    }
}
