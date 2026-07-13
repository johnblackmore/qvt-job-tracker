<div class="max-w-2xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">
            {{ $enquiry ? 'Edit Enquiry' : 'Log Enquiry' }}
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ $enquiry ? 'Update enquiry details' : 'Record a new customer enquiry' }}
        </p>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <div>
            <label for="customer_id" class="block text-sm font-medium text-slate-700 mb-1.5">Customer</label>
            <select wire:model="customer_id" id="customer_id" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                <option value="">-- Select customer (optional) --</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
            </select>
            @error('customer_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <input wire:model="email" id="email" type="email" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="customer@example.com" />
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-slate-700 mb-1.5">Phone</label>
                <input wire:model="phone" id="phone" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="07700 900000" />
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="source" class="block text-sm font-medium text-slate-700 mb-1.5">Source <span class="text-red-500">*</span></label>
                <select wire:model="source" id="source" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                    <option value="web">Website</option>
                    <option value="phone">Phone</option>
                    <option value="email">Email</option>
                    <option value="referral">Referral</option>
                    <option value="other">Other</option>
                </select>
                @error('source') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-slate-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                <select wire:model="status" id="status" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                    <option value="new">New</option>
                    <option value="in_progress">In Progress</option>
                    <option value="responded">Responded</option>
                    <option value="closed">Closed</option>
                </select>
                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="subject" class="block text-sm font-medium text-slate-700 mb-1.5">Subject</label>
            <input wire:model="subject" id="subject" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Brief subject line..." />
            @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="message" class="block text-sm font-medium text-slate-700 mb-1.5">Message <span class="text-red-500">*</span></label>
            <textarea wire:model="message" id="message" rows="5" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Details of the enquiry..."></textarea>
            @error('message') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="internal_notes" class="block text-sm font-medium text-slate-700 mb-1.5">Internal Notes</label>
            <textarea wire:model="internal_notes" id="internal_notes" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Staff-only notes (not visible to customer)..."></textarea>
            @error('internal_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $enquiry ? 'Save Changes' : 'Log Enquiry' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('enquiries.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
