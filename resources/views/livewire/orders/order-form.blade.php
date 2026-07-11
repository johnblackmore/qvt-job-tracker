<div class="max-w-3xl">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">{{ $order ? 'Edit Order' : 'Create Order' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $order ? 'Update order details' : 'Start a new installation order' }}</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="reference_number" class="block text-sm font-medium text-slate-700 mb-1.5">Reference <span class="text-red-500">*</span></label>
                    <input wire:model="reference_number" id="reference_number" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5 font-mono" placeholder="ORD-YYYYMMDD-XXXX" />
                    @error('reference_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                    <select wire:model="status" id="status" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5">
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
                    <select wire:model="customer_id" id="customer_id" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5">
                        <option value="">Select customer...</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="quote_id" class="block text-sm font-medium text-slate-700 mb-1.5">Linked quote</label>
                    <select wire:model="quote_id" id="quote_id" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5">
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
                    <input wire:model="total_amount" id="total_amount" type="number" step="0.01" min="0" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" />
                </div>
                <div>
                    <label for="deposit_required" class="block text-sm font-medium text-slate-700 mb-1.5">Deposit required (£) <span class="text-red-500">*</span></label>
                    <input wire:model="deposit_required" id="deposit_required" type="number" step="0.01" min="0" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" />
                </div>
                <div>
                    <label for="deposit_paid" class="block text-sm font-medium text-slate-700 mb-1.5">Deposit paid (£) <span class="text-red-500">*</span></label>
                    <input wire:model="deposit_paid" id="deposit_paid" type="number" step="0.01" min="0" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="scheduled_date" class="block text-sm font-medium text-slate-700 mb-1.5">Installation date</label>
                    <input wire:model="scheduled_date" id="scheduled_date" type="date" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" />
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea wire:model="notes" id="notes" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="Internal notes about this order..."></textarea>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $order ? 'Save Changes' : 'Create Order' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('orders.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
