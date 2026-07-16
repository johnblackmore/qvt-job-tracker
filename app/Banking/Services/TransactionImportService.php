<?php

namespace App\Banking\Services;

use App\Banking\Contracts\BankingProvider;
use App\Models\BankAccount;
use App\Models\BankTransaction;

class TransactionImportService
{
    private const CATEGORY_MAP = [
        'groceries' => 'subsistence',
        'eating_out' => 'subsistence',
        'transport' => 'travel',
        'shopping' => 'stock',
        'bills' => 'utilities',
        'cash' => 'other',
    ];

    public function import(BankAccount $account, BankingProvider $provider, array $params = []): array
    {
        $transactions = $provider->listTransactions(
            $account->provider_account_id,
            $params
        );

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($transactions as $txn) {
            $result = $this->importSingle($account, $txn);

            match ($result) {
                'imported' => $imported++,
                'skipped' => $skipped++,
                default => $errors++,
            };
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($transactions),
        ];
    }

    public function importSingle(BankAccount $account, array $txnData): string
    {
        $providerId = $txnData['id'] ?? null;

        if (! $providerId) {
            return 'error';
        }

        $exists = BankTransaction::where('provider_transaction_id', $providerId)->exists();

        if ($exists) {
            return 'skipped';
        }

        if (! empty($txnData['is_pending'])) {
            return 'skipped';
        }

        if (! empty($txnData['is_load'])) {
            return 'skipped';
        }

        $merchantName = $this->extractMerchantName($txnData);
        $category = $this->mapCategory($txnData['category'] ?? null);
        $settled = ! empty($txnData['settled']) ? $txnData['settled'] : null;

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'provider_transaction_id' => $providerId,
            'amount' => $this->normaliseAmount($txnData['amount'] ?? 0),
            'currency' => $txnData['currency'] ?? 'GBP',
            'description' => $txnData['description'] ?? '',
            'merchant_name' => $merchantName,
            'merchant_category' => $txnData['category'] ?? null,
            'transaction_date' => $txnData['created'] ?? now(),
            'settled_date' => $settled,
            'is_pending' => false,
            'is_load' => false,
            'metadata' => $txnData,
            'expense_category' => $category,
            'reconciliation_status' => 'unmatched',
            'imported_at' => now(),
        ]);

        return 'imported';
    }

    private function extractMerchantName(array $txnData): ?string
    {
        if (! empty($txnData['merchant']) && is_array($txnData['merchant'])) {
            return $txnData['merchant']['name'] ?? null;
        }

        return null;
    }

    private function mapCategory(?string $monzoCategory): ?string
    {
        if ($monzoCategory === null) {
            return null;
        }

        return self::CATEGORY_MAP[$monzoCategory] ?? null;
    }

    private function normaliseAmount(int $minorUnits): float
    {
        return $minorUnits / 100;
    }
}
