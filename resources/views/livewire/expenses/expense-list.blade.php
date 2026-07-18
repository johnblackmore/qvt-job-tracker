<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-ink tracking-tight">Expenses</h1>
            <p class="mt-1 text-sm text-slate-500">Track general business expenses and receipts</p>
        </div>
        <a href="{{ route('expenses.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
            <x-lucide-plus class="w-4 h-4" />
            New Expense
        </a>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-4 flex-wrap">
        <div class="relative flex-1 max-w-md">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search expenses..." class="w-full rounded-lg border-slate-300 text-ink placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
        </div>
        <select wire:model.live="categoryId" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="approved">Approved</option>
            <option value="paid">Paid</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <input wire:model.live="dateFrom" type="date" placeholder="From" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
        <input wire:model.live="dateTo" type="date" placeholder="To" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
        @if($search || $categoryId || $status || $dateFrom || $dateTo)
            <button wire:click="clearFilters" class="text-sm text-copper hover:underline">Clear filters</button>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($expenses->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Reference</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Merchant</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Category</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Date</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Amount</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Status</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($expenses as $expense)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <a href="{{ route('expenses.show', $expense->id) }}" wire:navigate class="font-medium text-copper hover:underline">
                                        {{ $expense->reference_number }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-ink">
                                    {{ $expense->merchant_name ?? '—' }}
                                    <div class="text-xs text-slate-500">{{ Str::limit($expense->description, 40) }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($expense->category)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $expense->category->name }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    {{ $expense->expense_date->format('j M Y') }}
                                </td>
                                <td class="px-6 py-4 font-medium text-ink">
                                    £{{ number_format($expense->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $expStatusStyles = [
                                            'draft' => 'bg-slate-100 text-slate-600',
                                            'approved' => 'bg-teal/10 text-teal-dark',
                                            'paid' => 'bg-teal/10 text-teal-dark',
                                            'cancelled' => 'bg-red-50 text-red-600',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $expStatusStyles[$expense->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ ucfirst($expense->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('expenses.show', $expense->id) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                            <x-lucide-eye class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('expenses.edit', $expense->id) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button wire:click="delete({{ $expense->id }})" wire:confirm="Delete this expense?" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $expenses->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-receipt class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-ink">No expenses yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Record your first business expense to start tracking outgoings.</p>
                <a href="{{ route('expenses.create') }}" wire:navigate class="mt-4 inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                    <x-lucide-plus class="w-4 h-4" />
                    New Expense
                </a>
            </div>
        @endif
    </div>
</div>
