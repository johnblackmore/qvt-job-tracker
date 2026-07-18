<?php

use App\Banking\Services\BalanceService;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Volt\Component;

new class extends Component
{
    public int $customerCount = 0;
    public int $openQuoteCount = 0;
    public int $pendingOrderCount = 0;
    public int $enquiryCount = 0;

    public Collection $bankAccounts;

    public bool $refreshing = false;

    public function mount(): void
    {
        $this->customerCount = Customer::count();
        $this->enquiryCount = Enquiry::whereIn('status', ['new', 'in_progress'])->count();
        $this->openQuoteCount = Quote::whereIn('status', ['draft', 'sent'])->count();
        $this->pendingOrderCount = Order::whereIn('status', ['pending', 'deposit_paid'])->count();

        $this->bankAccounts = app(BalanceService::class)->getBalances();
    }

    public function refreshBalances(): void
    {
        $this->refreshing = true;

        try {
            $this->bankAccounts = app(BalanceService::class)->refreshAllBalances();
            $this->dispatch('notify', message: 'Balances refreshed successfully.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Failed to refresh balances: '.$e->getMessage(), type: 'error');
        } finally {
            $this->refreshing = false;
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <p class="text-xs font-display font-semibold uppercase tracking-widest text-copper mb-1">Operations</p>
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Welcome back, {{ Auth::user()->name }}</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
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

        <a href="{{ route('quotes.index') }}" wire:navigate class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-colors block">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Open Quotes</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $openQuoteCount }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                    <x-lucide-file-text class="w-5 h-5 text-blue-600" />
                </div>
            </div>
        </a>

        <a href="{{ route('orders.index') }}" wire:navigate class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-colors block">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Pending Orders</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $pendingOrderCount }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                    <x-lucide-clipboard-list class="w-5 h-5 text-amber-600" />
                </div>
            </div>
        </a>

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

    {{-- Bank balances --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-8">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-base font-display font-semibold text-slate-900">Bank Accounts</h2>
            <button
                wire:click="refreshBalances"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-copper hover:text-copper-dark transition-colors disabled:opacity-50"
            >
                <x-lucide-refresh-cw class="w-4 h-4" wire:loading.class="animate-spin" />
                <span wire:loading.remove>Refresh</span>
                <span wire:loading>Refreshing...</span>
            </button>
        </div>
        <div class="p-6">
            @if ($bankAccounts->isEmpty())
                <p class="text-sm text-slate-500">No bank accounts linked yet.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    @foreach ($bankAccounts as $account)
                        <div class="p-4 rounded-lg border border-slate-200 bg-slate-50/50">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                                        <x-lucide-landmark class="w-4 h-4 text-teal-600" />
                                    </div>
                                    <p class="text-sm font-medium text-slate-700">{{ $account->name }}</p>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-slate-900 font-display tracking-tight">
                                @if ($account->balance_pence !== null)
                                    £{{ number_format($account->balance_pence / 100, 2) }}
                                @else
                                    <span class="text-slate-400 text-base font-normal">Pending</span>
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                @if ($account->balance_fetched_at)
                                    Updated {{ $account->balance_fetched_at->diffForHumans() }}
                                @else
                                    Not yet fetched
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
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
