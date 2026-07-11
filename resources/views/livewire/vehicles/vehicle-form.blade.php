<div class="max-w-lg">
    <div class="mb-8">
        <div class="flex items-center gap-2 mb-2">
            <a href="{{ route('customers.show', $customer) }}" wire:navigate class="text-sm text-slate-500 hover:text-emerald-600 transition-colors">
                {{ $customer->name }}
            </a>
            <x-lucide-chevron-right class="w-4 h-4 text-slate-400" />
            <span class="text-sm text-slate-900 font-medium">{{ $vehicle ? 'Edit Vehicle' : 'Add Vehicle' }}</span>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">
            {{ $vehicle ? 'Edit Vehicle' : 'Add Vehicle' }}
        </h1>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="make" class="block text-sm font-medium text-slate-700 mb-1.5">Make</label>
                <input wire:model="make" id="make" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="Ford, VW, Mercedes..." />
                @error('make') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="model" class="block text-sm font-medium text-slate-700 mb-1.5">Model</label>
                <input wire:model="model" id="model" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="Transit, Transporter..." />
                @error('model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="registration" class="block text-sm font-medium text-slate-700 mb-1.5">Registration</label>
                <input wire:model="registration" id="registration" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="AB12 CDE" />
                @error('registration') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="year" class="block text-sm font-medium text-slate-700 mb-1.5">Year</label>
                <input wire:model="year" id="year" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="2022" />
                @error('year') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="type" class="block text-sm font-medium text-slate-700 mb-1.5">Vehicle Type</label>
            <select wire:model="type" id="type" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5">
                <option value="">Select type...</option>
                <option value="small_van">Small van (Caddy, Connect, etc.)</option>
                <option value="medium_van">Medium van (Transporter, Transit Custom)</option>
                <option value="large_van">Large van (Transit, Sprinter, Crafter)</option>
                <option value="caravan">Caravan</option>
                <option value="car_suv">Car / SUV / MPV</option>
                <option value="other">Other</option>
            </select>
            @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
            <textarea wire:model="notes" id="notes" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="Any details about this vehicle..."></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $vehicle ? 'Save Changes' : 'Add Vehicle' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('customers.show', $customer) }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
