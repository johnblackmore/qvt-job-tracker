<?php

namespace App\Banking\Contracts;

interface BankingProvider
{
    public function name(): string;

    public function listAccounts(): array;

    public function getBalance(string $accountId): array;

    public function listTransactions(string $accountId, array $params = []): array;

    public function getTransaction(string $transactionId): array;

    public function registerWebhook(string $accountId, string $url): array;

    public function deleteWebhook(string $webhookId): void;
}
