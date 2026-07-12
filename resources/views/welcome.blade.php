<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-700 antialiased bg-slate-50">
        <div class="min-h-screen flex flex-col justify-center items-center px-4">
            <div class="text-center mb-8">
                <div class="w-20 h-20 rounded-xl bg-copper flex items-center justify-center shadow-lg mx-auto mb-4">
                    <x-lucide-bolt class="w-10 h-10 text-white" />
                </div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Quantock Van Tech</h1>
                <p class="mt-2 text-lg text-slate-500">Job Tracker</p>
                <p class="mt-4 text-sm text-slate-400 max-w-sm mx-auto">Staff-only admin system for managing customers, quotes, orders, and products.</p>
            </div>

            @auth
                <a
                    href="{{ route('dashboard') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-copper px-6 py-3 text-sm font-display font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors"
                >
                    <x-lucide-layout-dashboard class="w-4 h-4" />
                    Go to Dashboard
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-copper px-6 py-3 text-sm font-display font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors"
                >
                    <x-lucide-log-in class="w-4 h-4" />
                    Staff Sign In
                </a>
            @endauth
        </div>
    </body>
</html>
