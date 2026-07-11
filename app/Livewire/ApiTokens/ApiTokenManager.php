<?php

namespace App\Livewire\ApiTokens;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ApiTokenManager extends Component
{
    public string $tokenName = '';

    public ?string $newPlainTextToken = null;

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => 'required|string|max:255',
        ]);

        $token = Auth::user()->createToken($this->tokenName);

        $this->newPlainTextToken = $token->plainTextToken;
        $this->tokenName = '';

        $this->dispatch('token-created');
    }

    public function revokeToken(int $tokenId): void
    {
        $token = Auth::user()->tokens()->find($tokenId);

        if ($token) {
            $token->delete();
        }

        $this->dispatch('token-revoked');
    }

    public function clearNewToken(): void
    {
        $this->newPlainTextToken = null;
    }

    public function render(): View
    {
        return view('livewire.api-tokens.api-token-manager', [
            'tokens' => Auth::user()->tokens()->latest()->get(),
        ]);
    }
}
