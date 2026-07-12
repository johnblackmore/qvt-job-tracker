<div class="max-w-2xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">{{ $supplier ? 'Edit Supplier' : 'Add Supplier' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $supplier ? 'Update supplier details' : 'Create a new supplier record' }}</p>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">Company name <span class="text-red-500">*</span></label>
            <input wire:model="name" id="name" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Victron Energy" />
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="contact_name" class="block text-sm font-medium text-slate-700 mb-1.5">Contact name</label>
                <input wire:model="contact_name" id="contact_name" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="John Smith" />
                @error('contact_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-slate-700 mb-1.5">Phone</label>
                <input wire:model="phone" id="phone" type="tel" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="01234 567890" />
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <input wire:model="email" id="email" type="email" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="sales@supplier.com" />
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="website" class="block text-sm font-medium text-slate-700 mb-1.5">Website</label>
                <input wire:model="website" id="website" type="url" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="https://supplier.com" />
                @error('website') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-slate-700 mb-1.5">Address</label>
            <textarea wire:model="address" id="address" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Supplier address..."></textarea>
            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
            <textarea wire:model="notes" id="notes" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Trade account details, minimum orders, etc..."></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-slate-300 text-copper shadow-sm focus:ring-copper size-4" />
            <label for="is_active" class="text-sm text-slate-700">Active supplier</label>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $supplier ? 'Save Changes' : 'Create Supplier' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('suppliers.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
