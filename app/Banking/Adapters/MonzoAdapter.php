<?php

namespace App\Banking\Adapters;

use App\Banking\Contracts\BankingProvider;
use App\Models\BankAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MonzoAdapter implements BankingProvider
{
    private const BASE_URL = 'https://api.monzo.com';

    private const TOKEN_URL = 'https://api.monzo.com/oauth2/token';

    private const AUTH_URL = 'https://auth.monzo.com';

    private const CACHE_PREFIX = 'monzo_token_';

    private BankAccount $account;

    private PendingRequest $client;

    public function __construct(BankAccount $account)
    {
        $this->account = $account;
        $this->client = Http::baseUrl(self::BASE_URL)
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->retry(3, 100, function ($exception) {
                return $exception->response && in_array($exception->response->status(), [429, 500, 502, 503, 504]);
            })
            ->throw();
    }

    public function name(): string
    {
        return 'monzo';
    }

    public static function buildAuthUrl(string $clientId, string $redirectUri, string $state): string
    {
        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ]);

        return self::AUTH_URL.'?'.$params;
    }

    public static function exchangeAuthorizationCode(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $code,
    ): array {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ])->throw();

        return $response->json();
    }

    public function refreshAccessToken(string $clientId, string $clientSecret): array
    {
        $tokens = $this->getStoredTokens();
        $refreshToken = $tokens['refresh_token'] ?? null;

        if (! $refreshToken) {
            throw new \RuntimeException('No refresh token available for account '.$this->account->id);
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ])->throw();

        $newTokens = $response->json();
        $this->storeTokens($newTokens);

        return $newTokens;
    }

    public function listAccounts(): array
    {
        $response = $this->client->get('/accounts');

        return $response->json('accounts', []);
    }

    public function getBalance(string $accountId): array
    {
        $response = $this->client->get('/balance', [
            'account_id' => $accountId,
        ]);

        return $response->json();
    }

    public function listTransactions(string $accountId, array $params = []): array
    {
        $response = $this->client->get('/transactions', array_merge(
            ['account_id' => $accountId, 'expand[]' => 'merchant'],
            $params
        ));

        return $response->json('transactions', []);
    }

    public function getTransaction(string $transactionId): array
    {
        $response = $this->client->get("/transactions/{$transactionId}", [
            'expand[]' => 'merchant',
        ]);

        return $response->json('transaction', []);
    }

    public function registerWebhook(string $accountId, string $url): array
    {
        $response = $this->client->asForm()->post('/webhooks', [
            'account_id' => $accountId,
            'url' => $url,
        ]);

        return $response->json('webhook', []);
    }

    public function deleteWebhook(string $webhookId): void
    {
        $this->client->delete("/webhooks/{$webhookId}");
    }

    public function annotateTransaction(string $transactionId, array $metadata): void
    {
        $formData = [];
        foreach ($metadata as $key => $value) {
            $formData["metadata[{$key}]"] = $value;
        }

        $this->client->asForm()->patch("/transactions/{$transactionId}", $formData);
    }

    public function uploadAttachment(string $fileName, string $fileType, int $contentLength): array
    {
        $response = $this->client->asForm()->post('/attachment/upload', [
            'file_name' => $fileName,
            'file_type' => $fileType,
            'content_length' => $contentLength,
        ]);

        return $response->json();
    }

    public function registerAttachment(string $externalId, string $fileUrl, string $fileType): array
    {
        $response = $this->client->asForm()->post('/attachment/register', [
            'external_id' => $externalId,
            'file_url' => $fileUrl,
            'file_type' => $fileType,
        ]);

        return $response->json('attachment', []);
    }

    public function deregisterAttachment(string $attachmentId): void
    {
        $this->client->asForm()->post('/attachment/deregister', [
            'id' => $attachmentId,
        ]);
    }

    private function getAccessToken(): string
    {
        $tokens = $this->getStoredTokens();
        $expiresAt = $tokens['expires_at'] ?? 0;

        if ($expiresAt > now()->timestamp) {
            return $tokens['access_token'];
        }

        $clientId = config('banking.providers.monzo.client_id');
        $clientSecret = config('banking.providers.monzo.client_secret');

        $newTokens = $this->refreshAccessToken($clientId, $clientSecret);

        return $newTokens['access_token'];
    }

    private function getStoredTokens(): array
    {
        $metadata = $this->account->metadata;

        if (empty($metadata) || ! isset($metadata['tokens'])) {
            return [];
        }

        return $metadata['tokens'];
    }

    private function storeTokens(array $tokens): void
    {
        $metadata = $this->account->metadata ?? [];
        $metadata['tokens'] = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? ($metadata['tokens']['refresh_token'] ?? null),
            'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 21600)->timestamp,
            'client_id' => $tokens['client_id'] ?? null,
            'user_id' => $tokens['user_id'] ?? null,
        ];

        $this->account->updateQuietly(['metadata' => $metadata]);
    }
}
