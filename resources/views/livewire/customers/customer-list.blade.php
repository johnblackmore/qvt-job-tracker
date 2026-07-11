<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Customers</h1>
            <p class="mt-1 text-sm text-slate-500">Manage your customer records and their vehicles</p>
        </div>
        <a href="{{ route('customers.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
            <x-lucide-user-plus class="w-4 h-4" />
            Add Customer
        </a>
    </div>

    <div class="mb-6">
        <div class="relative max-w-md">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search by name, email, or phone..."
                class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-emerald-500 text-sm pl-9 pr-4 py-2.5"
            />
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($customers->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Name</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Contact</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-center">Vehicles</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-center">Enquiries</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-center">Quotes</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($customers as $customer)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <a href="{{ route('customers.show', $customer) }}" wire:navigate class="font-medium text-slate-900 hover:text-emerald-600 transition-colors">
                                        {{ $customer->name }}
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-0.5">
                                        @if($customer->email)
                                            <div class="text-slate-500 text-xs">{{ $customer->email }}</div>
                                        @endif
                                        @if($customer->phone)
                                            <div class="text-slate-500 text-xs">{{ $customer->phone }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $customer->vehicles_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $customer->enquiries_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $customer->quotes_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('customers.edit', $customer) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button
                                            wire:click="delete({{ $customer->id }})"
                                            wire:confirm="Are you sure you want to delete this customer? This will also delete all their vehicles and linked records."
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                        >
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
                {{ $customers->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-users class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No customers yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Add your first customer to start tracking enquiries, quotes, and orders.</p>
            </div>
        @endif
    </div>
</div>
