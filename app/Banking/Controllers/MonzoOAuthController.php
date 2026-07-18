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

    public function redirectReconnect(BankAccount $account)
    {
        $state = Str::random(40);
        session([
            'monzo_oauth_state' => $state,
            'reconnect_account_id' => $account->id,
            'reconnect_provider_account_id' => $account->provider_account_id,
        ]);

        $clientId = config('banking.providers.monzo.client_id');
        $redirectUri = $this->redirectUri();

        $authUrl = MonzoAdapter::buildAuthUrl($clientId, $redirectUri, $state);

        return redirect()->away($authUrl);
    }

    public function callback(Request $request)
    {
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

        $tokenData = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 21600)->timestamp,
            'client_id' => $tokens['client_id'] ?? null,
            'user_id' => $tokens['user_id'] ?? null,
        ];

        $pendingId = 'pending_'.Str::random(8);

        $account = BankAccount::create([
            'provider' => 'monzo',
            'provider_account_id' => $pendingId,
            'name' => 'Monzo Account',
            'metadata' => ['tokens' => $tokenData],
        ]);

        session(['pending_monzo_account_id' => $account->id]);

        return redirect()->route('admin.banking.approve');
    }

    public function retry()
    {
        $accountId = session('pending_monzo_account_id');
        $account = BankAccount::find($accountId);

        if (! $account) {
            return redirect()->route('admin.banking.transactions')
                ->with('error', 'Session expired. Please connect your Monzo account again.');
        }

        try {
            $adapter = new MonzoAdapter($account);
            $monzoAccounts = $adapter->listAccounts();
        } catch (\Exception $e) {
            return redirect()->route('admin.banking.approve')
                ->with('error', 'Could not retrieve accounts. Make sure you have approved the connection in your Monzo app, then try again.');
        }

        if (empty($monzoAccounts)) {
            return redirect()->route('admin.banking.transactions')
                ->with('warning', 'No Monzo accounts found on your profile.');
        }

        $reconnectAccountId = session('reconnect_account_id');
        $reconnectProviderId = session('reconnect_provider_account_id');

        if ($reconnectAccountId && $reconnectProviderId) {
            foreach ($monzoAccounts as $monzoAccount) {
                if (($monzoAccount['id'] ?? null) === $reconnectProviderId) {
                    return $this->completeReconnect(
                        $reconnectAccountId,
                        $account,
                        $monzoAccount,
                    );
                }
            }
        }

        $mapped = array_map(fn ($a) => [
            'id' => $a['id'],
            'description' => $a['description'] ?? 'Monzo Account',
            'account_type' => $a['type'] ?? 'uk_retail',
        ], $monzoAccounts);

        session(['pending_monzo_accounts' => $mapped]);
        session()->forget('pending_monzo_retry');

        return redirect()->route('admin.banking.select-account');
    }

    private function completeReconnect(int $existingAccountId, BankAccount $pending, array $monzoAccount): mixed
    {
        $existing = BankAccount::find($existingAccountId);

        if (! $existing) {
            return redirect()->route('admin.banking.select-account');
        }

        $existing->update([
            'metadata' => $pending->metadata,
            'is_active' => true,
        ]);

        $pending->delete();

        session()->forget([
            'pending_monzo_account_id',
            'pending_monzo_accounts',
            'pending_monzo_retry',
            'reconnect_account_id',
            'reconnect_provider_account_id',
        ]);

        try {
            $adapter = app(BankingProviderManager::class)->provider($existing);
            $importService = app(TransactionImportService::class);
            $importService->import($existing, $adapter, [
                'limit' => 100,
                'since' => now()->subDays(90)->format('Y-m-d\TH:i:s\Z'),
            ]);
        } catch (\Exception $e) {
            report($e);
        }

        return redirect()->route('admin.banking.transactions')
            ->with('success', 'Monzo account reconnected successfully.');
    }

    private function redirectUri(): string
    {
        return route('monzo.callback');
    }
}
