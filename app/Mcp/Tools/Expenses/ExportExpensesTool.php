<?php

namespace App\Mcp\Tools\Expenses;

use App\Models\Expense;
use App\Models\SupplierOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Export expenses and supplier orders as CSV for accounting software (QuickBooks/Xero compatible).')]
class ExportExpensesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()->description('Export from this date (YYYY-MM-DD)')->nullable(),
            'date_to' => $schema->string()->description('Export to this date (YYYY-MM-DD)')->nullable(),
            'type' => $schema->string()->description('Export type: all, supplier_orders, or expenses')->default('all'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'csv' => $schema->string()->description('CSV content for accounting import'),
            'record_count' => $schema->integer(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d'],
            'type' => ['required', 'in:all,supplier_orders,expenses'],
        ]);

        $rows = [];

        if (in_array($validated['type'], ['all', 'supplier_orders'])) {
            $orders = SupplierOrder::with('supplier')
                ->whereIn('status', ['received', 'partially_received', 'paid'])
                ->when($validated['date_from'], fn ($q, $d) => $q->whereDate('order_date', '>=', $d))
                ->when($validated['date_to'], fn ($q, $d) => $q->whereDate('order_date', '<=', $d))
                ->get();

            foreach ($orders as $order) {
                $rows[] = [
                    'Date' => $order->order_date->format('Y-m-d'),
                    'Reference' => $order->reference_number,
                    'Supplier' => $order->supplier?->name ?? 'Unknown',
                    'Description' => 'Supplier order',
                    'Net Amount' => $order->subtotal,
                    'VAT Amount' => $order->vat_total,
                    'Gross Amount' => $order->total_amount,
                    'Transaction Type' => 'supplier_order',
                    'Status' => $order->status,
                ];
            }
        }

        if (in_array($validated['type'], ['all', 'expenses'])) {
            $expenses = Expense::with('category')
                ->whereIn('status', ['approved', 'paid'])
                ->when($validated['date_from'], fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
                ->when($validated['date_to'], fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
                ->get();

            foreach ($expenses as $expense) {
                $rows[] = [
                    'Date' => $expense->expense_date->format('Y-m-d'),
                    'Reference' => $expense->reference_number,
                    'Supplier' => $expense->merchant_name ?? 'N/A',
                    'Description' => $expense->description,
                    'Net Amount' => $expense->total_amount - $expense->vat_total,
                    'VAT Amount' => $expense->vat_total,
                    'Gross Amount' => $expense->total_amount,
                    'Transaction Type' => 'expense',
                    'Status' => $expense->status,
                ];
            }
        }

        // Build CSV
        $headers = ['Date', 'Reference', 'Supplier', 'Description', 'Net Amount', 'VAT Amount', 'Gross Amount', 'Transaction Type', 'Status'];
        $csv = implode(',', $headers)."\n";

        foreach ($rows as $row) {
            $escaped = array_map(function ($val) {
                return '"'.str_replace('"', '""', $val).'"';
            }, array_values($row));
            $csv .= implode(',', $escaped)."\n";
        }

        return Response::structured([
            'status' => 'completed',
            'message' => 'Exported '.count($rows).' records as CSV.',
            'csv' => $csv,
            'record_count' => count($rows),
        ]);
    }
}
