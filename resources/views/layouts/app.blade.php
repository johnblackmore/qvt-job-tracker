<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-700 antialiased bg-slate-50">
        <div class="min-h-screen flex">
            {{-- Sidebar --}}
            <aside
                x-data="{ open: false }"
                @keydown.window.escape="open = false"
                class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:flex lg:flex-col"
                :class="open ? 'translate-x-0' : '-translate-x-full'"
            >
                {{-- Logo --}}
                <div class="flex items-center gap-3 h-16 px-6 border-b border-slate-200 shrink-0">
                    <div class="w-8 h-8 rounded-lg bg-copper flex items-center justify-center">
                        <x-lucide-bolt class="w-5 h-5 text-white" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-slate-900 leading-none">QVT</span>
                        <span class="text-[10px] text-slate-500 leading-none mt-0.5">Job Tracker</span>
                    </div>
                </div>

                {{-- Navigation --}}
                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                    <a
                        href="{{ route('dashboard') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-layout-dashboard class="w-5 h-5 shrink-0" />
                        Dashboard
                    </a>

                    <a
                        href="{{ route('customers.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('customers.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-users class="w-5 h-5 shrink-0" />
                        Customers
                    </a>

                    <a
                        href="{{ route('enquiries.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('enquiries.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-mail class="w-5 h-5 shrink-0" />
                        Enquiries
                    </a>

                    <a
                        href="{{ route('quotes.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('quotes.*', 'sample-quotes.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-file-text class="w-5 h-5 shrink-0" />
                        Quotes
                    </a>

                    <a
                        href="{{ route('orders.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('orders.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-clipboard-list class="w-5 h-5 shrink-0" />
                        Orders
                    </a>

                    <a
                        href="{{ route('products.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('products.*') && !request()->routeIs('products.categories.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-package class="w-5 h-5 shrink-0" />
                        Products
                    </a>

                    <div class="border-t border-slate-200 my-3"></div>
                    <div class="px-3 pb-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Admin</span>
                    </div>

                    <a
                        href="{{ route('products.categories.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('products.categories.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-folder class="w-5 h-5 shrink-0" />
                        Categories
                    </a>

                    <a
                        href="{{ route('suppliers.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('suppliers.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-building-2 class="w-5 h-5 shrink-0" />
                        Suppliers
                    </a>

                    <a
                        href="{{ route('email-templates.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('email-templates.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-mail-plus class="w-5 h-5 shrink-0" />
                        Email Templates
                    </a>

                    <button
                        x-on:click="Livewire.dispatch('toggle-chat')"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-slate-600 hover:bg-slate-100 hover:text-slate-900 w-full text-left"
                    >
                        <x-lucide-message-square-more class="w-5 h-5 shrink-0" />
                        AI Assistant
                    </button>

                    <a
                        href="{{ route('admin.api-tokens') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.api-tokens') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-key class="w-5 h-5 shrink-0" />
                        AI Agent Access
                    </a>
                </nav>

                {{-- User menu --}}
                <div class="border-t border-slate-200 p-3 shrink-0">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <div class="w-8 h-8 rounded-full bg-copper/15 flex items-center justify-center shrink-0">
                            <span class="text-xs font-semibold text-copper-dark">{{ substr(Auth::user()->name, 0, 1) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</p>
                        </div>
                    </div>
                    <div class="mt-1 space-y-0.5">
                        <a
                            href="{{ route('profile') }}"
                            wire:navigate
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors"
                        >
                            <x-lucide-settings class="w-4 h-4 shrink-0" />
                            Profile Settings
                        </a>
                        <livewire:actions.logout />
                    </div>
                </div>
            </aside>

            {{-- Mobile overlay --}}
            <div
                x-show="open"
                x-transition.opacity.duration.200ms
                @click="open = false"
                class="fixed inset-0 z-40 bg-black/30 lg:hidden"
                style="display: none;"
            ></div>

            {{-- Main content --}}
            <div class="flex-1 flex flex-col min-w-0">
                {{-- Top bar --}}
                <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 shrink-0">
                    <button
                        @click="open = !open"
                        class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                    >
                        <x-lucide-menu class="w-5 h-5" />
                    </button>

                    <div class="flex items-center gap-4">
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-copper/10 text-copper border border-copper/20">
                            {{ Auth::user()->getRoleNames()->first() ?? 'Staff' }}
                        </span>
                    </div>
                </header>

                {{-- Toast notifications --}}
                <div
                    x-data="{ toasts: [], add(message, type = 'success') { this.toasts.push({ id: Date.now(), message, type }); setTimeout(() => this.remove(this.toasts[0]?.id), 4000); }, remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); } }"
                    x-on:notify.window="add($event.detail.message, $event.detail.type)"
                    class="fixed top-4 right-4 z-[60] space-y-3 w-full max-w-sm pointer-events-none"
                >
                    <template x-for="toast in toasts" :key="toast.id">
                        <div
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="translate-x-full opacity-0"
                            x-transition:enter-end="translate-x-0 opacity-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="translate-x-0 opacity-100"
                            x-transition:leave-end="translate-x-full opacity-0"
                            class="pointer-events-auto flex items-center gap-3 rounded-lg shadow-lg border px-4 py-3 text-sm font-medium"
                            :class="toast.type === 'success' ? 'bg-white border-teal/20 text-teal-dark' : toast.type === 'error' ? 'bg-white border-red-200 text-red-800' : 'bg-white border-slate-200 text-slate-800'"
                        >
                            <span x-show="toast.type === 'success'" class="text-teal">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span x-show="toast.type === 'error'" class="text-red-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </span>
                            <span x-text="toast.message"></span>
                        </div>
                    </template>
                </div>

                {{-- Page content --}}
                <main class="flex-1 overflow-y-auto p-4 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @auth
            @if(Auth::user()->hasRole('admin'))
                <livewire:chat.chat-widget />
            @endif
        @endauth
    </body>
</html>
