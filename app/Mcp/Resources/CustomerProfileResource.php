<?php

namespace App\Mcp\Resources;

use App\Models\Customer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class CustomerProfileResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('qvt://customers/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = (int) $request->get('id');

        $customer = Customer::with(['vehicles', 'enquiries', 'quotes', 'orders'])
            ->findOrFail($id);

        $data = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'notes' => $customer->notes,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
            'url' => route('customers.show', $customer),
            'vehicles' => $customer->vehicles->map(fn ($v) => [
                'id' => $v->id,
                'make' => $v->make,
                'model' => $v->model,
                'registration' => $v->registration,
                'year' => $v->year,
            ]),
            'enquiries' => $customer->enquiries->map(fn ($e) => [
                'id' => $e->id,
                'subject' => $e->subject,
                'status' => $e->status,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
            'quotes' => $customer->quotes->map(fn ($q) => [
                'id' => $q->id,
                'reference_number' => $q->reference_number,
                'status' => $q->status,
                'grand_total' => (float) $q->grand_total,
            ]),
            'orders' => $customer->orders->map(fn ($o) => [
                'id' => $o->id,
                'reference_number' => $o->reference_number,
                'status' => $o->status,
                'total_amount' => (float) $o->total_amount,
            ]),
        ];

        return Response::text(json_encode($data, JSON_PRETTY_PRINT));
    }
}
