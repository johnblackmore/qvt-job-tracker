<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
                    <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center">
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
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-layout-dashboard class="w-5 h-5 shrink-0" />
                        Dashboard
                    </a>

                    <a
                        href="{{ route('customers.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('customers.*') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-users class="w-5 h-5 shrink-0" />
                        Customers
                    </a>

                    <a
                        href="#"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors opacity-50 cursor-not-allowed"
                    >
                        <x-lucide-file-text class="w-5 h-5 shrink-0" />
                        Quotes
                    </a>

                    <a
                        href="#"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors opacity-50 cursor-not-allowed"
                    >
                        <x-lucide-clipboard-list class="w-5 h-5 shrink-0" />
                        Orders
                    </a>

                    <a
                        href="{{ route('products.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('products.*') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-package class="w-5 h-5 shrink-0" />
                        Products
                    </a>

                    <a
                        href="{{ route('enquiries.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('enquiries.*') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        <x-lucide-mail class="w-5 h-5 shrink-0" />
                        Enquiries
                    </a>
                </nav>

                {{-- User menu --}}
                <div class="border-t border-slate-200 p-3 shrink-0">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                            <span class="text-xs font-semibold text-emerald-700">{{ substr(Auth::user()->name, 0, 1) }}</span>
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
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            {{ Auth::user()->getRoleNames()->first() ?? 'Staff' }}
                        </span>
                    </div>
                </header>

                {{-- Page content --}}
                <main class="flex-1 overflow-y-auto p-4 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
