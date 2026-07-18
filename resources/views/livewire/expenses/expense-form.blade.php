<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-ink tracking-tight">{{ $expense ? 'Edit Expense' : 'New Expense' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $expense ? 'Update expense details' : 'Record a new business expense' }}</p>
    </div>

    @if(!$expense)
        <div class="mb-6">
            <livewire:expenses.ai-extraction-panel />
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <h2 class="text-base font-display font-semibold text-ink">Expense Details</h2>

            <div>
                <label for="merchant_name" class="block text-sm font-medium text-slate-700 mb-1.5">Merchant name</label>
                <input wire:model="merchant_name" id="merchant_name" type="text" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="B&Q, Halfords, etc..." />
                @error('merchant_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Description <span class="text-red-500">*</span></label>
                <textarea wire:model="description" id="description" rows="2" required class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="What was this expense for?"></textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="expense_category_id" class="block text-sm font-medium text-slate-700 mb-1.5">Category</label>
                    <select wire:model="expense_category_id" id="expense_category_id" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="">Select a category...</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expense_date" class="block text-sm font-medium text-slate-700 mb-1.5">Expense date <span class="text-red-500">*</span></label>
                    <input wire:model="expense_date" id="expense_date" type="date" required class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                    @error('expense_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="total_amount" class="block text-sm font-medium text-slate-700 mb-1.5">Total amount (£)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">£</span>
                        <input wire:model="total_amount" id="total_amount" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm pl-7 pr-3.5 py-2.5" />
                    </div>
                    @error('total_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="vat_total" class="block text-sm font-medium text-slate-700 mb-1.5">VAT total (£)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">£</span>
                        <input wire:model="vat_total" id="vat_total" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm pl-7 pr-3.5 py-2.5" />
                    </div>
                    @error('vat_total') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-slate-700 mb-1.5">Payment method</label>
                    <select wire:model="payment_method" id="payment_method" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="">Select...</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="cash">Cash</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="payment_reference" class="block text-sm font-medium text-slate-700 mb-1.5">Payment reference</label>
                    <input wire:model="payment_reference" id="payment_reference" type="text" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Transaction ref..." />
                </div>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                <select wire:model="status" id="status" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                    <option value="paid">Paid</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea wire:model="notes" id="notes" rows="2" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Additional details..."></textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Document Upload --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-base font-display font-semibold text-ink mb-4">Receipt / Invoice</h2>
            <div>
                <input wire:model="upload" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-copper file:text-white hover:file:bg-copper-dark file:cursor-pointer" />
                @error('upload') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-xs text-slate-500">PDF, JPG or PNG (max 10MB)</p>
            </div>
        </div>

        {{-- Line Items Toggle --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-display font-semibold text-ink">Line Items</h2>
                    <p class="text-xs text-slate-500">Break this expense into multiple items (e.g., for mixed business/personal purchases)</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:click="toggleLineItems" {{ $showLineItems ? 'checked' : '' }} class="sr-only peer" />
                    <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-copper rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-copper"></div>
                </label>
            </div>

            @if($showLineItems)
                <div class="flex items-center justify-end mb-3">
                    <button type="button" wire:click="addLineItem" class="inline-flex items-center gap-1.5 text-sm font-medium text-copper hover:text-copper-dark">
                        <x-lucide-plus class="w-4 h-4" />
                        Add Item
                    </button>
                </div>

                @if(count($lineItems) > 0)
                    <div class="space-y-2">
                        @foreach($lineItems as $index => $item)
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-50">
                                <div class="flex-1 space-y-2">
                                    <input wire:model="lineItems.{{ $index }}.description" type="text" class="w-full rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-2.5 py-1.5" placeholder="Item description..." />
                                    @error("lineItems.{$index}.description") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    <div class="flex flex-wrap gap-2 items-start">
                                        <select wire:model="lineItems.{{ $index }}.line_type" class="rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-xs px-2 py-1.5">
                                            <option value="business">Business</option>
                                            <option value="personal">Personal</option>
                                        </select>
                                        <input wire:model="lineItems.{{ $index }}.quantity" wire:change="recalculateLineItem({{ $index }})" type="number" step="0.01" min="0" class="w-16 text-right rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-2 py-1.5" placeholder="Qty" />
                                        <div class="relative max-w-[120px]">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs">£</span>
                                            <input wire:model="lineItems.{{ $index }}.unit_price" wire:change="recalculateLineItem({{ $index }})" type="number" step="0.0001" min="0" class="w-full text-right rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm pl-5 pr-2 py-1.5" placeholder="Unit price" />
                                        </div>
                                        <div class="relative max-w-[120px]">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs">£</span>
                                            <input wire:model="lineItems.{{ $index }}.amount" wire:change="recalculateLineItem({{ $index }})" type="number" step="0.01" min="0" class="w-full text-right rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm pl-5 pr-2 py-1.5" placeholder="Total" />
                                        </div>
                                        <input wire:model="lineItems.{{ $index }}.vat_rate" wire:change="recalculateLineItem({{ $index }})" type="number" step="0.01" min="0" max="100" class="w-16 text-right rounded border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-2 py-1.5" placeholder="VAT%" />
                                        <span class="text-sm text-slate-600 self-center">VAT: £{{ number_format((float) ($item['vat_amount'] ?? 0), 2) }}</span>
                                    </div>
                                </div>
                                <button type="button" wire:click="removeLineItem({{ $index }})" class="p-1 rounded text-slate-400 hover:text-red-600 transition-colors mt-1">
                                    <x-lucide-x class="w-4 h-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500 text-center py-4">No line items added yet.</p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $expense ? 'Save Changes' : 'Create Expense' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('expenses.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
