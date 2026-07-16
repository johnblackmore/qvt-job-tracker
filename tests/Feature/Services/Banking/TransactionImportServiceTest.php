<?php

namespace Tests\Feature\Services\Banking;

use App\Banking\Services\TransactionImportService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransactionImportService::class);
    }

    public function test_import_single_creates_new_transaction(): void
    {
        $account = BankAccount::factory()->create();

        $monzoTxn = $this->buildMonzoTransaction();

        $result = $this->service->importSingle($account, $monzoTxn);

        $this->assertEquals('imported', $result);

        $this->assertDatabaseHas('bank_transactions', [
            'provider_transaction_id' => $monzoTxn['id'],
            'bank_account_id' => $account->id,
            'reconciliation_status' => 'unmatched',
        ]);
    }

    public function test_import_single_skips_duplicate_provider_transaction_id(): void
    {
        $account = BankAccount::factory()->create();
        $monzoTxn = $this->buildMonzoTransaction();

        $this->service->importSingle($account, $monzoTxn);
        $result = $this->service->importSingle($account, $monzoTxn);

        $this->assertEquals('skipped', $result);

        $this->assertEquals(1, BankTransaction::where('provider_transaction_id', $monzoTxn['id'])->count());
    }

    public function test_import_single_skips_pending_transactions(): void
    {
        $account = BankAccount::factory()->create();
        $monzoTxn = $this->buildMonzoTransaction(['is_pending' => true, 'settled' => '']);

        $result = $this->service->importSingle($account, $monzoTxn);

        $this->assertEquals('skipped', $result);
    }

    public function test_import_single_skips_load_transactions(): void
    {
        $account = BankAccount::factory()->create();
        $monzoTxn = $this->buildMonzoTransaction(['is_load' => true]);

        $result = $this->service->importSingle($account, $monzoTxn);

        $this->assertEquals('skipped', $result);
    }

    public function test_import_single_maps_category_correctly(): void
    {
        $account = BankAccount::factory()->create();

        $mappings = [
            'eating_out' => 'subsistence',
            'groceries' => 'subsistence',
            'transport' => 'travel',
            'shopping' => 'stock',
            'bills' => 'utilities',
            'cash' => 'other',
            'entertainment' => null,
        ];

        foreach ($mappings as $monzoCat => $expectedCat) {
            $monzoTxn = $this->buildMonzoTransaction(['id' => 'tx_'.uniqid(), 'category' => $monzoCat]);
            $this->service->importSingle($account, $monzoTxn);
        }

        foreach ($mappings as $monzoCat => $expectedCat) {
            $txn = BankTransaction::where('merchant_category', $monzoCat)->first();
            $this->assertNotNull($txn);
            $this->assertEquals($expectedCat, $txn->expense_category);
        }
    }

    public function test_import_normalises_amount_from_minor_units(): void
    {
        $account = BankAccount::factory()->create();
        $monzoTxn = $this->buildMonzoTransaction(['amount' => -12345]);

        $this->service->importSingle($account, $monzoTxn);

        $this->assertDatabaseHas('bank_transactions', [
            'provider_transaction_id' => $monzoTxn['id'],
            'amount' => -123.45,
        ]);
    }

    public function test_import_extracts_merchant_name_when_expanded(): void
    {
        $account = BankAccount::factory()->create();
        $monzoTxn = $this->buildMonzoTransaction();

        $this->service->importSingle($account, $monzoTxn);

        $txn = BankTransaction::where('provider_transaction_id', $monzoTxn['id'])->first();
        $this->assertNotNull($txn);
        $this->assertEquals('Ozone Coffee Roasters', $txn->merchant_name);
    }

    public function test_import_skips_missing_id(): void
    {
        $account = BankAccount::factory()->create();

        $result = $this->service->importSingle($account, ['description' => 'no id']);

        $this->assertEquals('error', $result);
    }

    private function buildMonzoTransaction(array $overrides = []): array
    {
        return array_merge([
            'id' => 'tx_'.uniqid(),
            'amount' => -350,
            'currency' => 'GBP',
            'description' => 'Ozone Coffee Roasters',
            'created' => '2026-07-15T14:28:40Z',
            'settled' => '2026-07-16T14:28:40Z',
            'category' => 'eating_out',
            'is_load' => false,
            'is_pending' => false,
            'merchant' => [
                'id' => 'merch_00008zjky19HyFLAzlUk7t',
                'name' => 'Ozone Coffee Roasters',
                'category' => 'eating_out',
                'address' => [
                    'address' => '11 Leonard Street',
                    'city' => 'London',
                    'country' => 'GB',
                    'postcode' => 'EC2A 4AQ',
                ],
            ],
        ], $overrides);
    }
}
