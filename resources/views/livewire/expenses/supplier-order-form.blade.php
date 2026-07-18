<div class="max-w-4xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-ink tracking-tight">{{ $supplierOrder ? 'Edit Supplier Order' : 'New Supplier Order' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $supplierOrder ? 'Update order details and line items' : 'Record a new supplier order or invoice' }}</p>
    </div>

    @if(!$supplierOrder)
        <div class="mb-6">
            <livewire:expenses.ai-extraction-panel />
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{-- Order Details --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <h2 class="text-base font-display font-semibold text-ink">Order Details</h2>

            <div>
                <label for="supplier_id" class="block text-sm font-medium text-slate-700 mb-1.5">Supplier</label>
                <select wire:model="supplier_id" id="supplier_id" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                    <option value="">Select a supplier...</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @error('supplier_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="order_date" class="block text-sm font-medium text-slate-700 mb-1.5">Order date <span class="text-red-500">*</span></label>
                    <input wire:model="order_date" id="order_date" type="date" required class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                    @error('order_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="invoice_date" class="block text-sm font-medium text-slate-700 mb-1.5">Invoice date</label>
                    <input wire:model="invoice_date" id="invoice_date" type="date" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                    @error('invoice_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="due_date" class="block text-sm font-medium text-slate-700 mb-1.5">Due date</label>
                    <input wire:model="due_date" id="due_date" type="date" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                    @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="invoice_number" class="block text-sm font-medium text-slate-700 mb-1.5">Invoice number</label>
                <input wire:model="invoice_number" id="invoice_number" type="text" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="INV-001234" />
                @error('invoice_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                <select wire:model="status" id="status" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                    <option value="draft">Draft</option>
                    <option value="ordered">Ordered</option>
                    <option value="received">Received</option>
                    <option value="partially_received">Partially Received</option>
                    <option value="paid">Paid</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea wire:model="notes" id="notes" rows="3" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Delivery notes, payment terms, etc..."></textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Document Upload --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-base font-display font-semibold text-ink mb-4">Invoice Document</h2>
            <div>
                <input wire:model="upload" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-copper file:text-white hover:file:bg-copper-dark file:cursor-pointer" />
                @error('upload') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-xs text-slate-500">PDF, JPG or PNG (max 10MB)</p>
            </div>
        </div>

        {{-- Line Items --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-display font-semibold text-ink">Line Items</h2>
                <button type="button" wire:click="addLineItem" class="inline-flex items-center gap-1.5 text-sm font-medium text-copper hover:text-copper-dark">
                    <x-lucide-plus class="w-4 h-4" />
                    Add Line
                </button>
            </div>

            @error('lineItems') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

            @if(count($lineItems) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200">
                                <th class="pb-2 text-left font-medium text-slate-600 pr-2">Type</th>
                                <th class="pb-2 text-left font-medium text-slate-600 pr-2 w-full">Description</th>
                                <th class="pb-2 text-right font-medium text-slate-600 pr-2">Qty</th>
                                <th class="pb-2 text-right font-medium text-slate-600 pr-2">Unit Price</th>
                                <th class="pb-2 text-right font-medium text-slate-600 pr-2">VAT %</th>
                                <th class="pb-2 text-right font-medium text-slate-600 pr-2">VAT</th>
                                <th class="pb-2 text-right font-medium text-slate-600">Total</th>
                                <th class="pb-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lineItems as $index => $item)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2 pr-2">
                                        <select wire:model="lineItems.{{ $index }}.line_type" wire:change="recalculateLineItem({{ $index }})" class="rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-xs px-2 py-1.5">
                                            <option value="product">Product</option>
                                            <option value="service">Service</option>
                                            <option value="expense">Expense</option>
                                            <option value="personal">Personal</option>
                                        </select>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input wire:model="lineItems.{{ $index }}.description" type="text" class="w-full rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-2.5 py-1.5" placeholder="Item description..." />
                                        @error("lineItems.{$index}.description") <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input wire:model="lineItems.{{ $index }}.quantity" wire:change="recalculateLineItem({{ $index }})" type="number" step="1" min="1" class="w-16 text-right rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-2 py-1.5" />
                                    </td>
                                    <td class="py-2 pr-2">
                                        <div class="relative">
                                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">£</span>
                                            <input wire:model="lineItems.{{ $index }}.unit_amount" wire:change="recalculateLineItem({{ $index }})" type="number" step="0.01" min="0" class="w-24 text-right rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm pl-6 pr-2 py-1.5" />
                                        </div>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input wire:model="lineItems.{{ $index }}.vat_rate" wire:change="recalculateLineItem({{ $index }})" type="number" step="0.01" min="0" max="100" class="w-16 text-right rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-2 py-1.5" />
                                    </td>
                                    <td class="py-2 pr-2 text-right text-slate-600 text-xs">
                                        £{{ number_format((float) ($item['vat_amount'] ?? 0), 2) }}
                                    </td>
                                    <td class="py-2 text-right font-medium text-ink">
                                        £{{ number_format((float) ($item['line_total'] ?? 0), 2) }}
                                    </td>
                                    <td class="py-2 pl-2">
                                        @if(count($lineItems) > 1)
                                            <button type="button" wire:click="removeLineItem({{ $index }})" class="p-1 rounded text-slate-400 hover:text-red-600 transition-colors">
                                                <x-lucide-x class="w-4 h-4" />
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-medium">
                                <td colspan="5" class="pt-3 text-right text-slate-600">Subtotal:</td>
                                <td class="pt-3 text-right text-ink">£{{ number_format((float) $subtotal, 2) }}</td>
                                <td></td>
                            </tr>
                            <tr class="font-medium">
                                <td colspan="5" class="text-right text-slate-600">VAT:</td>
                                <td class="text-right text-ink">£{{ number_format((float) $vat_total, 2) }}</td>
                                <td></td>
                            </tr>
                            <tr class="font-semibold text-base">
                                <td colspan="5" class="pt-1 text-right text-ink">Total:</td>
                                <td class="pt-1 text-right text-ink">£{{ number_format((float) $total_amount, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500 text-center py-8">No line items yet. Click "Add Line" to start building this order.</p>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $supplierOrder ? 'Save Changes' : 'Create Order' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('expenses.supplier-orders.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
