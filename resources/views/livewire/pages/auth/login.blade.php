<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Welcome back</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to your staff account</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
            <input
                wire:model="form.email"
                id="email"
                type="email"
                required
                autofocus
                autocomplete="username"
                class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5"
                placeholder="admin@quantockvantech.com"
            />
            <x-input-error :messages="$errors->get('form.email')" class="mt-1.5" />
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
            <input
                wire:model="form.password"
                id="password"
                type="password"
                required
                autocomplete="current-password"
                class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5"
                placeholder="••••••••"
            />
            <x-input-error :messages="$errors->get('form.password')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-between">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input
                    wire:model="form.remember"
                    type="checkbox"
                    class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 size-4"
                />
                <span class="text-sm text-slate-600">Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a
                    href="{{ route('password.request') }}"
                    wire:navigate
                    class="text-sm font-medium text-emerald-600 hover:text-emerald-700"
                >
                    Forgot password?
                </a>
            @endif
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors"
        >
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in...</span>
        </button>
    </form>
</div>
