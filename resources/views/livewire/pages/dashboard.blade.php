<?php

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Quote;
use Livewire\Volt\Component;

new class extends Component
{
    public int $customerCount = 0;
    public int $openQuoteCount = 0;
    public int $pendingOrderCount = 0;
    public int $enquiryCount = 0;

    public function mount(): void
    {
        $this->customerCount = Customer::count();
        $this->enquiryCount = Enquiry::whereIn('status', ['new', 'in_progress'])->count();
        $this->openQuoteCount = Quote::whereIn('status', ['draft', 'sent'])->count();
        $this->pendingOrderCount = Order::whereIn('status', ['pending', 'deposit_paid'])->count();
    }
}; ?>

<div>
    <div class="mb-8">
        <p class="text-xs font-display font-semibold uppercase tracking-widest text-copper mb-1">Operations</p>
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Welcome back, {{ Auth::user()->name }}</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <a href="{{ route('customers.index') }}" wire:navigate class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Customers</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $customerCount }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center">
                    <x-lucide-users class="w-5 h-5 text-copper" />
                </div>
            </div>
        </a>

        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Open Quotes</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $openQuoteCount }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                    <x-lucide-file-text class="w-5 h-5 text-blue-600" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Pending Orders</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $pendingOrderCount }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                    <x-lucide-clipboard-list class="w-5 h-5 text-amber-600" />
                </div>
            </div>
        </div>

        <a href="{{ route('enquiries.index') }}" wire:navigate class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Open Enquiries</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $enquiryCount }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                    <x-lucide-inbox class="w-5 h-5 text-purple-600" />
                </div>
            </div>
        </a>
    </div>

    {{-- Quick actions --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-base font-display font-semibold text-slate-900">Quick Actions</h2>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('customers.create') }}" wire:navigate class="group flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:border-copper/30 hover:bg-copper/10/50 transition-all">
                <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center group-hover:bg-copper/15 transition-colors">
                    <x-lucide-user-plus class="w-5 h-5 text-copper" />
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">Add Customer</p>
                    <p class="text-xs text-slate-500">Create a new customer record</p>
                </div>
            </a>

            <a href="{{ route('quotes.create') }}" wire:navigate class="group flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:border-copper/30 hover:bg-copper/10/50 transition-all">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                    <x-lucide-file-plus class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">Create Quote</p>
                    <p class="text-xs text-slate-500">Build a new customer quote</p>
                </div>
            </a>

            <a href="{{ route('enquiries.create') }}" wire:navigate class="group flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:border-copper/30 hover:bg-copper/10/50 transition-all">
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center group-hover:bg-purple-100 transition-colors">
                    <x-lucide-plus-circle class="w-5 h-5 text-purple-600" />
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">Add Enquiry</p>
                    <p class="text-xs text-slate-500">Log a new customer enquiry</p>
                </div>
            </a>
        </div>
    </div>

</div>
