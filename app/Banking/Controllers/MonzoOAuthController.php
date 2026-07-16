<?php

namespace App\Banking\Controllers;

use App\Banking\Adapters\MonzoAdapter;
use App\Banking\Services\BankingProviderManager;
use App\Banking\Services\TransactionImportService;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class MonzoOAuthController extends Controller
{
    public function redirect()
    {
        $state = Str::random(40);
        session(['monzo_oauth_state' => $state]);

        $clientId = config('banking.providers.monzo.client_id');
        $redirectUri = $this->redirectUri();

        $authUrl = MonzoAdapter::buildAuthUrl($clientId, $redirectUri, $state);

        return redirect()->away($authUrl);
    }

    public function callback(
        Request $request,
        BankingProviderManager $providerManager,
        TransactionImportService $importService,
    ) {
        $storedState = session('monzo_oauth_state');

        if ($request->error) {
            return redirect()->route('admin.banking.transactions')
                ->with('error', 'Monzo authorisation was cancelled or declined.');
        }

        if (! $storedState || $request->state !== $storedState) {
            return redirect()->route('admin.banking.transactions')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }

        session()->forget('monzo_oauth_state');

        $clientId = config('banking.providers.monzo.client_id');
        $clientSecret = config('banking.providers.monzo.client_secret');
        $redirectUri = $this->redirectUri();

        $tokens = MonzoAdapter::exchangeAuthorizationCode(
            $clientId,
            $clientSecret,
            $redirectUri,
            $request->code,
        );

        $tempAccount = new BankAccount;
        $tempAccount->provider = 'monzo';
        $tempAccount->provider_account_id = 'pending';
        $tempAccount->name = 'Monzo Account';
        $tempAccount->metadata = ['tokens' => [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 21600)->timestamp,
            'client_id' => $tokens['client_id'] ?? null,
            'user_id' => $tokens['user_id'] ?? null,
        ]];
        $tempAccount->save();

        try {
            $adapter = new MonzoAdapter($tempAccount);

            $monzoAccounts = $adapter->listAccounts();

            $monzoAccount = $monzoAccounts[0] ?? null;

            if (! $monzoAccount) {
                $tempAccount->update([
                    'metadata' => array_merge($tempAccount->metadata ?? [], ['tokens' => [
                        'access_token' => $tokens['access_token'],
                        'refresh_token' => $tokens['refresh_token'] ?? null,
                        'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 21600)->timestamp,
                        'client_id' => $tokens['client_id'] ?? null,
                        'user_id' => $tokens['user_id'] ?? null,
                    ]]),
                ]);

                return redirect()->route('admin.banking.transactions')
                    ->with('warning', 'Monzo account linked, but no accounts were found.');
            }

            $tempAccount->update([
                'provider_account_id' => $monzoAccount['id'],
                'name' => $monzoAccount['description'] ?? 'Monzo Account',
                'type' => 'current',
                'metadata' => array_merge($tempAccount->metadata ?? [], [
                    'tokens' => [
                        'access_token' => $tokens['access_token'],
                        'refresh_token' => $tokens['refresh_token'] ?? null,
                        'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 21600)->timestamp,
                        'client_id' => $tokens['client_id'] ?? null,
                        'user_id' => $tokens['user_id'] ?? null,
                    ],
                    'monzo_account' => $monzoAccount,
                ]),
            ]);

            try {
                $importService->import($tempAccount, $adapter, [
                    'limit' => 100,
                    'since' => now()->subDays(90)->format('Y-m-d\TH:i:s\Z'),
                ]);
            } catch (\Exception $e) {
                // Import failure is non-fatal — user can run it manually via the UI
            }

            return redirect()->route('admin.banking.transactions')
                ->with('success', 'Monzo account linked successfully. Recent transactions have been imported.');
        } catch (\Exception $e) {
            $tempAccount->delete();

            return redirect()->route('admin.banking.transactions')
                ->with('error', 'Failed to link Monzo account: '.$e->getMessage());
        }
    }

    private function redirectUri(): string
    {
        return route('monzo.callback');
    }
}
