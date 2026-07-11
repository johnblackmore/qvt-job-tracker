<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Fetch a single customer record by ID, including their vehicles, enquiries, quotes, and orders.')]
class GetCustomerTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The customer ID.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'url' => $schema->string(),
            'customer' => $schema->object([]),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:customers,id',
        ]);

        $customer = Customer::with(['vehicles', 'enquiries', 'quotes', 'orders'])
            ->findOrFail($validated['id']);

        return Response::structured([
            'status' => 'completed',
            'message' => "Retrieved customer {$customer->name}.",
            'url' => route('customers.show', $customer),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'created_at' => $customer->created_at?->toIso8601String(),
                'updated_at' => $customer->updated_at?->toIso8601String(),
                'vehicles' => $customer->vehicles->map(function ($vehicle) {
                    return [
                        'id' => $vehicle->id,
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'registration' => $vehicle->registration,
                        'year' => $vehicle->year,
                    ];
                }),
                'enquiries' => $customer->enquiries->map(function ($enquiry) {
                    return [
                        'id' => $enquiry->id,
                        'subject' => $enquiry->subject,
                        'status' => $enquiry->status,
                        'created_at' => $enquiry->created_at?->toIso8601String(),
                    ];
                }),
                'quotes' => $customer->quotes->map(function ($quote) {
                    return [
                        'id' => $quote->id,
                        'reference_number' => $quote->reference_number,
                        'status' => $quote->status,
                        'grand_total' => $quote->grand_total,
                        'created_at' => $quote->created_at?->toIso8601String(),
                    ];
                }),
                'orders' => $customer->orders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'reference_number' => $order->reference_number,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'scheduled_date' => $order->scheduled_date?->toDateString(),
                    ];
                }),
            ],
        ]);
    }
}
