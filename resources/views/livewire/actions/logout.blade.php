<?php

use App\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<button
    wire:click="logout"
    class="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors"
>
    <x-lucide-log-out class="w-4 h-4 shrink-0" />
    Log Out
</button>
