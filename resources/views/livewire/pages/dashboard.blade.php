<?php

use Livewire\Volt\Component;

new class extends Component
{
    public int $customerCount = 0;
    public int $openQuoteCount = 0;
    public int $pendingOrderCount = 0;
    public int $enquiryCount = 0;

    public function mount(): void
    {
        // Placeholder values until models are created in later phases
        $this->customerCount = 0;
        $this->openQuoteCount = 0;
        $this->pendingOrderCount = 0;
        $this->enquiryCount = 0;
    }
}; ?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Welcome back, {{ Auth::user()->name }}</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Customers</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $customerCount }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <x-lucide-users class="w-5 h-5 text-emerald-600" />
                </div>
            </div>
        </div>

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

        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">New Enquiries</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $enquiryCount }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                    <x-lucide-inbox class="w-5 h-5 text-purple-600" />
                </div>
            </div>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-base font-semibold text-slate-900">Quick Actions</h2>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="#" class="group flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/50 transition-all">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-100 transition-colors">
                    <x-lucide-user-plus class="w-5 h-5 text-emerald-600" />
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">Add Customer</p>
                    <p class="text-xs text-slate-500">Create a new customer record</p>
                </div>
            </a>

            <a href="#" class="group flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/50 transition-all">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                    <x-lucide-file-plus class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">Create Quote</p>
                    <p class="text-xs text-slate-500">Build a new customer quote</p>
                </div>
            </a>

            <a href="#" class="group flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/50 transition-all">
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

    {{-- Empty states placeholder --}}
    <div class="mt-8 bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-base font-semibold text-slate-900">Recent Activity</h2>
        </div>
        <div class="p-12 text-center">
            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                <x-lucide-clock class="w-6 h-6 text-slate-400" />
            </div>
            <h3 class="text-sm font-medium text-slate-900">No recent activity</h3>
            <p class="mt-1 text-sm text-slate-500">Activity will appear here once you start creating customers, quotes, and orders.</p>
        </div>
    </div>
</div>
