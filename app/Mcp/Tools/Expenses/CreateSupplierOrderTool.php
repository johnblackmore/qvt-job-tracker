<?php

namespace App\Mcp\Tools\Expenses;

use App\Models\Supplier;
use App\Models\SupplierOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Create a new supplier order with optional line items. Requires confirmation after preview.')]
class CreateSupplierOrderTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier_id' => $schema->integer()->description('The supplier ID')->nullable(),
            'order_date' => $schema->string()->description('Order date (YYYY-MM-DD). Defaults to today.')->nullable(),
            'invoice_number' => $schema->string()->description('Supplier invoice reference')->nullable(),
            'notes' => $schema->string()->description('Internal notes')->nullable(),
            'status' => $schema->string()->description('Status: draft, ordered, received, paid')->default('draft'),
            'line_items' => $schema->array($schema->object([
                'description' => $schema->string()->required(),
                'quantity' => $schema->number()->default(1),
                'unit_amount' => $schema->number()->default(0),
                'vat_rate' => $schema->number()->description('VAT rate as percentage (e.g. 20)')->default(20),
                'line_type' => $schema->string()->description('product, service, expense, or personal')->default('product'),
            ]))->description('Line items on the order')->nullable(),
            'preview' => $schema->boolean()->description('Preview without saving')->default(true),
            'confirmed' => $schema->boolean()->description('Confirm to execute')->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error']),
            'message' => $schema->string(),
            'url' => $schema->string()->nullable(),
            'order' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'order_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,ordered,received,paid'],
            'line_items' => ['nullable', 'array'],
            'line_items.*.description' => ['required', 'string'],
            'line_items.*.quantity' => ['numeric', 'min:0.001'],
            'line_items.*.unit_amount' => ['numeric', 'min:0'],
            'line_items.*.vat_rate' => ['numeric', 'min:0', 'max:100'],
            'line_items.*.line_type' => ['in:product,service,expense,personal'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error('Set preview=true to review or confirmed=true to proceed.');
        }

        $supplier = $validated['supplier_id'] ? Supplier::find($validated['supplier_id']) : null;
        $reference = 'PO-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        $orderDate = $validated['order_date'] ?? now()->format('Y-m-d');

        $items = $validated['line_items'] ?? [];
        $subtotal = 0;
        $vatTotal = 0;
        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unit = (float) ($item['unit_amount'] ?? 0);
            $vatRate = (float) ($item['vat_rate'] ?? 20) / 100;
            $lineNet = $qty * $unit;
            $subtotal += $lineNet;
            $vatTotal += $lineNet * $vatRate;
        }
        $totalAmount = $subtotal + $vatTotal;

        if ($isPreview && ! $isConfirmed) {
            $msg = 'I will create a supplier order';
            if ($supplier) {
                $msg .= " from {$supplier->name}";
            }
            $msg .= ".\n\nReference: {$reference}\nDate: {$orderDate}\nTotal: £".number_format($totalAmount, 2);
            if (count($items) > 0) {
                $msg .= "\nLine items: ".count($items);
            }

            return Response::structured([
                'status' => 'preview',
                'message' => $msg."\n\nIs that correct?",
                'data' => [
                    'reference_number' => $reference,
                    'supplier_name' => $supplier?->name,
                    'order_date' => $orderDate,
                    'total_amount' => $totalAmount,
                    'line_items' => count($items),
                ],
            ]);
        }

        $order = SupplierOrder::create([
            'reference_number' => $reference,
            'supplier_id' => $validated['supplier_id'],
            'order_date' => $orderDate,
            'invoice_number' => $validated['invoice_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'],
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total_amount' => $totalAmount,
            'created_by_user_id' => $request->user()?->id,
        ]);

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unit = (float) ($item['unit_amount'] ?? 0);
            $vatRate = (float) ($item['vat_rate'] ?? 20) / 100;
            $lineNet = $qty * $unit;
            $order->lineItems()->create([
                'line_type' => $item['line_type'] ?? 'product',
                'description' => $item['description'],
                'quantity' => $qty,
                'unit_amount' => $unit,
                'vat_rate' => $vatRate,
                'vat_amount' => $lineNet * $vatRate,
                'line_total' => $lineNet + ($lineNet * $vatRate),
            ]);
        }

        return Response::structured([
            'status' => 'completed',
            'message' => "Supplier order {$reference} created for £".number_format($totalAmount, 2).'.',
            'url' => route('expenses.supplier-orders.show', $order),
            'order' => $order->fresh('lineItems')->toArray(),
        ]);
    }
}
