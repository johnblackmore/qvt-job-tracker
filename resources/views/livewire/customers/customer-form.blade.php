<div class="max-w-2xl">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">
            {{ $customer ? 'Edit Customer' : 'Add Customer' }}
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ $customer ? 'Update customer details' : 'Create a new customer record' }}
        </p>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">Full name <span class="text-red-500">*</span></label>
            <input
                wire:model="name"
                id="name"
                type="text"
                required
                class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5"
                placeholder="John Smith"
            />
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5"
                    placeholder="john@example.com"
                />
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-slate-700 mb-1.5">Phone number</label>
                <input
                    wire:model="phone"
                    id="phone"
                    type="tel"
                    class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5"
                    placeholder="01984 600327"
                />
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-slate-700 mb-1.5">Address</label>
            <textarea
                wire:model="address"
                id="address"
                rows="3"
                class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5"
                placeholder="Williton, West Somerset"
            ></textarea>
            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
            <textarea
                wire:model="notes"
                id="notes"
                rows="3"
                class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5"
                placeholder="Any additional notes about this customer..."
            ></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors"
            >
                <span wire:loading.remove>{{ $customer ? 'Save Changes' : 'Create Customer' }}</span>
                <span wire:loading>Saving...</span>
            </button>

            <a href="{{ route('customers.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>
