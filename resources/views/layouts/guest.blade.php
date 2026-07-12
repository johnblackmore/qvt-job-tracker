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
        <div class="min-h-screen flex flex-col justify-center items-center px-4">
            <div class="mb-8 text-center">
                <a href="/" wire:navigate class="inline-flex flex-col items-center gap-2">
                    <div class="w-16 h-16 rounded-xl bg-copper flex items-center justify-center shadow-lg">
                        <x-lucide-bolt class="w-9 h-9 text-white" />
                    </div>
                    <span class="text-xl font-bold text-slate-900 tracking-tight">Quantock Van Tech</span>
                    <span class="text-sm text-slate-500">Job Tracker</span>
                </a>
            </div>

            <div class="w-full max-w-md">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
