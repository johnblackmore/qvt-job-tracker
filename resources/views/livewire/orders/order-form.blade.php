<div class="max-w-3xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">{{ $order ? 'Edit Order' : 'Create Order' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $order ? 'Update order details' : 'Start a new installation order' }}</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="reference_number" class="block text-sm font-medium text-slate-700 mb-1.5">Reference <span class="text-red-500">*</span></label>
                    <input wire:model="reference_number" id="reference_number" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5 font-mono" placeholder="ORD-YYYYMMDD-XXXX" />
                    @error('reference_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                    <select wire:model="status" id="status" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="pending">Pending</option>
                        <option value="deposit_paid">Deposit Paid</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-slate-700 mb-1.5">Customer <span class="text-red-500">*</span></label>
                    <select wire:model="customer_id" id="customer_id" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="">Select customer...</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="quote_id" class="block text-sm font-medium text-slate-700 mb-1.5">Linked quote</label>
                    <select wire:model="quote_id" id="quote_id" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="">None</option>
                        @foreach($quotes as $quote)
                            <option value="{{ $quote->id }}">{{ $quote->reference_number }} — {{ $quote->customer->name }} (£{{ number_format($quote->grand_total, 2) }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="total_amount" class="block text-sm font-medium text-slate-700 mb-1.5">Total amount (£) <span class="text-red-500">*</span></label>
                    <input wire:model="total_amount" id="total_amount" type="number" step="0.01" min="0" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>
                <div>
                    <label for="deposit_required" class="block text-sm font-medium text-slate-700 mb-1.5">Deposit required (£) <span class="text-red-500">*</span></label>
                    <input wire:model="deposit_required" id="deposit_required" type="number" step="0.01" min="0" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>
                <div>
                    <label for="deposit_paid" class="block text-sm font-medium text-slate-700 mb-1.5">Deposit paid (£) <span class="text-red-500">*</span></label>
                    <input wire:model="deposit_paid" id="deposit_paid" type="number" step="0.01" min="0" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="scheduled_date" class="block text-sm font-medium text-slate-700 mb-1.5">Installation date</label>
                    <input wire:model="scheduled_date" id="scheduled_date" type="date" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea wire:model="notes" id="notes" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Internal notes about this order..."></textarea>
            </div>
        </div>

        @if($order)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-display font-semibold text-slate-900">Payments ({{ count($payments) }})</h2>
                    @if(! $showPaymentForm)
                        <button type="button" wire:click="openPaymentForm" class="inline-flex items-center gap-1.5 rounded-lg bg-copper px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                            <x-lucide-plus class="w-3.5 h-3.5" />
                            Record Payment
                        </button>
                    @endif
                </div>

                @if($showPaymentForm)
                    <div class="rounded-lg border border-copper/20 bg-copper/5 p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-700 uppercase tracking-wide">New Payment</span>
                            <button type="button" wire:click="closePaymentForm" class="p-1 rounded text-slate-400 hover:text-slate-600">
                                <x-lucide-x class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Amount (£)</label>
                                <input wire:model="newPaymentAmount" type="number" step="0.01" min="0.01" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-1.5" />
                                @error('newPaymentAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Method</label>
                                <select wire:model="newPaymentMethod" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-1.5">
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="card">Card</option>
                                    <option value="cash">Cash</option>
                                    <option value="other">Other</option>
                                </select>
                                @error('newPaymentMethod') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Reference (optional)</label>
                                <input wire:model="newPaymentReference" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-1.5" placeholder="Bank ref / transaction ID" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Date paid</label>
                                <input wire:model="newPaymentDate" type="date" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-1.5" />
                                @error('newPaymentDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button type="button" wire:click="closePaymentForm" class="px-3 py-1.5 text-xs font-medium text-slate-600 hover:text-slate-900 transition-colors">Cancel</button>
                            <button type="button" wire:click="recordPayment" class="inline-flex items-center gap-1.5 rounded-lg bg-copper px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                                <x-lucide-check class="w-3.5 h-3.5" />
                                Record Payment
                            </button>
                        </div>
                    </div>
                @endif

                @if(count($payments) > 0)
                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-slate-700">Date</th>
                                    <th class="px-4 py-2 text-left font-medium text-slate-700">Method</th>
                                    <th class="px-4 py-2 text-left font-medium text-slate-700">Reference</th>
                                    <th class="px-4 py-2 text-right font-medium text-slate-700">Amount</th>
                                    <th class="px-4 py-2 text-right font-medium text-slate-700"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($payments as $payment)
                                    <tr>
                                        <td class="px-4 py-2.5 text-slate-600">{{ \Carbon\Carbon::parse($payment['paid_at'])->format('d M Y') }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide
                                                {{ $payment['method'] === 'bank_transfer' ? 'bg-blue-50 text-blue-700' : '' }}
                                                {{ $payment['method'] === 'card' ? 'bg-purple-50 text-purple-700' : '' }}
                                                {{ $payment['method'] === 'cash' ? 'bg-teal/10 text-teal-dark' : '' }}
                                                {{ $payment['method'] === 'other' ? 'bg-slate-100 text-slate-600' : '' }}
                                            ">
                                                {{ str_replace('_', ' ', $payment['method']) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-500 text-xs">{{ $payment['reference'] ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-medium text-slate-900">£{{ number_format((float) $payment['amount'], 2) }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            <button type="button" wire:click="removePayment({{ $payment['id'] }})" wire:confirm="Remove this payment record?" class="p-1 rounded text-slate-400 hover:text-red-600 transition-colors">
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-50 border-t border-slate-200">
                                <tr>
                                    <td colspan="3" class="px-4 py-2.5 text-xs font-medium text-slate-700">Total paid</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-slate-900">£{{ number_format(collect($payments)->sum('amount'), 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-slate-500 text-center py-4">No payments recorded yet.</p>
                @endif
            </div>
        @endif

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $order ? 'Save Changes' : 'Create Order' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('orders.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
