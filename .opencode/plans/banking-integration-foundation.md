# Banking Integration Foundation Plan

**Status:** Ready for implementation
**Date:** 2026-07-16
**Decisions:** See [Resolved Questions](#resolved-questions) below

---

## Overview

Build a bank-agnostic banking integration layer into QVT Job Tracker, starting with a Monzo adapter. Three features in scope:

| # | Feature | Value |
|---|---------|-------|
| 1 | **Automatic Transaction Import & Categorisation** | Eliminate manual bookkeeping; all business spending in one place |
| 2 | **Account Reconciliation** | Ensure all customer payments are accounted for; simplify period-end reconciliation |
| 3 | **Receipt Capture & Document Management** | Complete digital audit trail linking receipts to bank transactions |

---

## Architecture: Bank-Agnostic Core + Monzo Adapter

```
app/
  Banking/
    Contracts/
      BankingProvider.php
    Adapters/
      MonzoAdapter.php
    Models/
      BankAccount.php
      BankTransaction.php
    Services/
      BankingProviderManager.php
      TransactionImportService.php
      ReconciliationService.php
    Livewire/
      Banking/
        TransactionList.php
        TransactionShow.php
        ReconciliationView.php
    Mcp/
      Tools/
        Banking/
          ImportTransactionsTool.php
          ListTransactionsTool.php
          GetTransactionTool.php
          UpdateTransactionCategoryTool.php
          ReconcilePaymentTool.php
          ListUnmatchedTransactionsTool.php
          GetReconciliationSummaryTool.php
          AttachReceiptTool.php
    Console/
      ImportTransactionsCommand.php
    Webhooks/
      MonzoWebhookController.php

routes/
  banking.php
```

---

## BankingProvider Interface

Every banking provider implements this contract. The `BankingProviderManager` resolves the correct adapter by provider name.

```php
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
```

Monzo-specific extras (not on core interface — for adapter-specific features):
- `annotateTransaction(string $transactionId, array $metadata): void`
- `uploadAttachment(string $filePath, string $mimeType): array`
- `registerAttachment(string $transactionId, string $fileUrl, string $fileType): array`
- `deregisterAttachment(string $attachmentId): void`

---

## Data Model

### `bank_accounts`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| provider | string(50) | e.g. `monzo` |
| provider_account_id | string(255) | Monzo `acc_xxx` |
| name | string(255) | "QVT Business Account" |
| type | string(50) | `current`, `savings`, `joint` |
| currency | char(3) | `GBP` |
| is_active | boolean | default `true` |
| metadata | json (encrypted) | Provider tokens + raw account data |
| timestamps | | |

**Indexes:** `(provider, provider_account_id)` unique

### `bank_transactions`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| bank_account_id | FK→bank_accounts | |
| provider_transaction_id | string(255) | Unique per provider |
| amount | decimal(10,2) | Negative=debit, Positive=credit |
| currency | char(3) | `GBP` |
| description | string(500) | Raw merchant descriptor |
| merchant_name | string(255), nullable | Parsed from description or provider data |
| merchant_category | string(100), nullable | e.g. `eating_out`, `transport`, `shopping` |
| transaction_date | datetime | |
| settled_date | datetime, nullable | |
| is_pending | boolean | |
| is_load | boolean | Top-ups |
| notes | text, nullable | Staff notes |
| metadata | json, nullable | Raw provider data as-is |
| expense_category | string(50), nullable | `stock`, `equipment`, `travel`, `fuel`, `subsistence`, `utilities`, `professional_fees`, `insurance`, `other` |
| reconciliation_status | string(20) | `unmatched`, `matched`, `ignored` |
| matched_payment_id | FK→payments, nullable | Direct link when reconciled |
| imported_at | timestamp | |
| timestamps | | |

**Indexes:** `(provider_transaction_id)` unique, `(bank_account_id, transaction_date)`, `(reconciliation_status)`

### `payments` — add `bank_transaction_id`
| Column | Type | Notes |
|--------|------|-------|
| bank_transaction_id | FK→bank_transactions, nullable | NEW — links payment to its bank transaction |

---

## Resolved Questions

| # | Question | Decision |
|---|----------|----------|
| 1 | Transaction storage model? | **New `bank_transactions` table** — normalised, bank-agnostic, queryable |
| 2 | How to link payments to transactions? | **`bank_transaction_id` FK on `payments`** — simple, leverages existing payment model |
| 3 | MCP tools or just Livewire? | **Both** — MCP tools alongside Livewire UI, full parity |
| 4 | Monzo OAuth token storage? | **Encrypted JSON in `bank_accounts.metadata`** — self-contained, supports multiple accounts |
| 5 | Receipt file storage? | **Local `storage/app/receipts/`** with public symlink. Can migrate to S3 later via Laravel filesystem abstraction |
| 6 | Auto-reconciliation strategy? | **Amount ±£0.01 + 3-day window + merchant name pattern** — flag ambiguous matches for manual review |

---

## Phase A — BankingProvider Contract & MonzoAdapter

| Step | Description | Files |
|------|-------------|-------|
| A1 | Create `BankingProvider` interface | `app/Banking/Contracts/BankingProvider.php` |
| A2 | Create `MonzoAdapter` (OAuth token management, HTTP client via Laravel's `Http` facade) | `app/Banking/Adapters/MonzoAdapter.php` |
| A3 | Create `BankingProviderManager` (factory/provider registry) | `app/Banking/Services/BankingProviderManager.php` |
| A4 | Config file for Monzo client credentials | `config/banking.php` |
| A5 | Create `BankAccount` model + migration | `app/Banking/Models/BankAccount.php` |

### MonzoAdapter Key Behaviour

- **OAuth flow:** Exchanges auth code for tokens; stores encrypted in `BankAccount.metadata`
- **Token refresh:** Before every API call, checks `expires_in` and refreshes if needed; updates metadata with new tokens
- **Retry:** Exponential backoff on 429 (rate limit) and 5xx
- **Base URL:** `https://api.monzo.com`
- **All rate limits respected** (no documented limit on Developer API, but we implement our own 30 req/min per provider guard)

### Config (`config/banking.php`)

```php
return [
    'default' => env('BANKING_DEFAULT_PROVIDER', 'monzo'),
    'providers' => [
        'monzo' => [
            'client_id' => env('MONZO_CLIENT_ID'),
            'client_secret' => env('MONZO_CLIENT_SECRET'),
            'redirect_uri' => env('MONZO_REDIRECT_URI'),
        ],
    ],
];
```

---

## Phase B — Transaction Import (Idea #1)

| Step | Description | Files |
|------|-------------|-------|
| B1 | Create `BankTransaction` model + migration | `app/Banking/Models/BankTransaction.php` |
| B2 | Implement `MonzoAdapter::listTransactions()` + `getTransaction()` | MonzoAdapter |
| B3 | Create `TransactionImportService` (deduplication, normalisation, category mapping) | `app/Banking/Services/TransactionImportService.php` |
| B4 | Register Monzo webhook route at `/webhooks/monzo` (no CSRF, throttled) | `routes/banking.php` + `bootstrap/app.php` |
| B5 | Create `MonzoWebhookController` (receives `transaction.created` events) | `app/Banking/Webhooks/MonzoWebhookController.php` |
| B6 | Create `artisan banking:import` command (sync recent 90 days) | `app/Banking/Console/ImportTransactionsCommand.php` |
| B7 | Schedule `banking:import` hourly in console kernel | `routes/console.php` |
| B8 | Create `TransactionList` Livewire component (filterable, paginated, with expense category filter) | `app/Banking/Livewire/TransactionList.php` + view |
| B9 | Create `TransactionShow` Livewire component (details, edit notes/category, link to order) | `app/Banking/Livewire/TransactionShow.php` + view |
| B10 | Create MCP `ImportTransactionsTool` (preview shows count of new transactions) | `app/Banking/Mcp/Tools/ImportTransactionsTool.php` |
| B11 | Create MCP `ListTransactionsTool` (read-only, filterable) | `app/Banking/Mcp/Tools/ListTransactionsTool.php` |
| B12 | Create MCP `GetTransactionTool` (read-only) | `app/Banking/Mcp/Tools/GetTransactionTool.php` |
| B13 | Create MCP `UpdateTransactionCategoryTool` (preview/confirmed) | `app/Banking/Mcp/Tools/UpdateTransactionCategoryTool.php` |

### Import Deduplication Logic

```
1. Check `provider_transaction_id` unique constraint — if exists, skip
2. Skip transactions where `is_pending = true` (wait for settled)
3. Skip transactions where `is_load = true` (top-ups — not business expenses)
4. Map Monzo `category` to our `expense_category`:
   - `groceries`, `eating_out` → `subsistence`
   - `transport` → `travel`
   - `shopping` → `stock` (default; staff can override)
   - `bills` → `utilities`
   - `cash` → `other`
   - `general`, `expenses`, `entertainment`, `holidays` → unset (staff assigns)
5. Extract `merchant_name` from Monzo expanded merchant (if `expand[]=merchant` used), else parse from `description`
6. Set `reconciliation_status = 'unmatched'` for new transactions
```

### Webhook Processing

On receiving `transaction.created`:
1. Verify webhook signature (if Monzo provides one) or use shared secret via URL path
2. Decode JSON payload
3. Check dedup by `data.id` (provider_transaction_id)
4. Create `BankTransaction` record
5. Run auto-reconciliation (Phase C) to check if it matches a pending payment
6. Return 200 OK

### Sidebar Navigation

Add a "Banking" section in `resources/views/layouts/app.blade.php`:

```
Banking
├── Transactions    → /admin/banking/transactions
├── Reconciliation  → /admin/banking/reconciliation
```

---

## Phase C — Account Reconciliation (Idea #2)

| Step | Description | Files |
|------|-------------|-------|
| C1 | Add `bank_transaction_id` FK to `payments` table (nullable) | Migration |
| C2 | Update `Payment` model with `bankTransaction()` relationship | `app/Models/Payment.php` |
| C3 | Create `ReconciliationService` (auto-match + manual match methods) | `app/Banking/Services/ReconciliationService.php` |
| C4 | Auto-match logic: amount ±£0.01 tolerance, within 3-day window, merchant name pattern | `ReconciliationService::autoMatch()` |
| C5 | Create `ReconciliationView` Livewire component (split-panel UI) | `app/Banking/Livewire/ReconciliationView.php` + view |
| C6 | Implement manual link action in ReconciliationView | ReconciliationView |
| C7 | Create MCP `ReconcilePaymentTool` (preview/confirmed) | `app/Banking/Mcp/Tools/ReconcilePaymentTool.php` |
| C8 | Create MCP `ListUnmatchedTransactionsTool` (read-only) | `app/Banking/Mcp/Tools/ListUnmatchedTransactionsTool.php` |
| C9 | Create MCP `GetReconciliationSummaryTool` (read-only) | `app/Banking/Mcp/Tools/GetReconciliationSummaryTool.php` |

### Auto-Match Algorithm

```
For each unmatched BankTransaction where amount < 0 (debits only):
    Find all Payments where:
        - abs(bank_txn.amount - payment.amount) <= 0.01
        - bank_txn.transaction_date is within 3 days of payment.paid_at
        - payment.bank_transaction_id IS NULL (not already matched)

    If exactly 1 match found:
        Auto-link (set bank_transaction_id)
        Set reconciliation_status = 'matched'
        Log match

    If multiple matches found:
        Keep as 'unmatched'
        Flag for manual review (store candidate IDs in metadata)

    If no match found:
        Keep as 'unmatched'
```

### ReconciliationView UI

```
┌──────────────────────────────────────────────────────┐
│  Reconciliation  [Date Range: ████████████] [Filter] │
├──────────────────────┬───────────────────────────────┤
│  Unmatched Payments  │  Unmatched Transactions       │
│  (from orders)       │  (from bank)                  │
│                      │                               │
│  £500.00  02/07      │  -£500.00  Monzo  02/07      │
│  Order #ORD-...      │  "Bathroom store"             │
│                      │                               │
│  [Link]              │  -£12.99  Monzo  03/07        │
│                      │  "Pret A Manger"              │
│  £1,200.00  05/07    │                               │
│  Order #ORD-...      │  [Ignore] [Categorise]        │
└──────────────────────┴───────────────────────────────┘
```

Auto-matched items not shown in the split panel (shown in a separate "Recently Matched" section).

---

## Phase D — Receipt Capture (Idea #3)

| Step | Description | Files |
|------|-------------|-------|
| D1 | Create `receipts` table (file path, bank_transaction_id, filename, mime, size) | Migration + `app/Models/Receipt.php` |
| D2 | Add receipt upload to TransactionShow Livewire component | `TransactionShow.php` + view |
| D3 | Implement `MonzoAdapter::uploadAttachment()` + `registerAttachment()` | MonzoAdapter |
| D4 | On receipt upload: validate file, store locally, create Receipt record, push to Monzo attachment API (async via queue job) | `TransactionImportService` or new `ReceiptService` |

### Receipts Table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| bank_transaction_id | FK→bank_transactions, nullable | |
| file_path | string(500) | Relative path in storage |
| original_filename | string(255) | User-uploaded name |
| mime_type | string(100) | |
| file_size | integer | Bytes |
| notes | text, nullable | |
| timestamps | | |

### Monzo Sync (Queue Job)

When a receipt is uploaded:
1. Save file to `storage/app/receipts/{bank_account_id}/{transaction_id}/`
2. Create `Receipt` record
3. Dispatch queue job: `SyncReceiptToMonzo`
4. Job calls `MonzoAdapter::uploadAttachment()` → gets S3 upload URL
5. Job uploads file to S3 URL
6. Job calls `MonzoAdapter::registerAttachment()` → links to Monzo transaction
7. Job stores Monzo attachment ID on `Receipt.metadata` for potential deregistration

**Failure handling:** If Monzo sync fails, receipt still exists locally. Queue retries with backoff. After 3 failures, marks as `sync_failed` and logs error.

---

## Routes

```php
// routes/banking.php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin/banking')->name('admin.banking.')->group(function () {
    Route::get('/transactions', App\Banking\Livewire\TransactionList::class)->name('transactions');
    Route::get('/transactions/{transaction}', App\Banking\Livewire\TransactionShow::class)->name('transactions.show');
    Route::get('/reconciliation', App\Banking\Livewire\ReconciliationView::class)->name('reconciliation');
});

// Webhook — no CSRF, throttled
Route::post('/webhooks/monzo', [App\Banking\Webhooks\MonzoWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:60,1');
```

Registered in `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    // ... existing
    then: function () {
        Route::middleware('web')->group(base_path('routes/banking.php'));
    },
)
```

---

## MCP Tool Inventory

| Tool | Phase | Read/Write | Description |
|------|-------|------------|-------------|
| `import-transactions` | B | Write | Import recent transactions from linked bank account. Preview shows count of new transactions that will be imported. |
| `list-transactions` | B | Read | List bank transactions with filters (date range, expense category, reconciliation status, amount range). Paginated. |
| `get-transaction` | B | Read | Get single transaction details including linked receipt and payment info. |
| `update-transaction-category` | B | Write | Set expense category on a transaction (stock/equipment/travel/fuel/etc). Preview confirms current vs new category. |
| `reconcile-payment` | C | Write | Match a bank transaction to an order payment. Preview shows transaction and payment details for confirmation. |
| `list-unmatched-transactions` | C | Read | Show all transactions not yet matched to any payment, grouped by age (recent/aging/stale). |
| `get-reconciliation-summary` | C | Read | Summary of matched/unmatched/ignored counts for a date range, with total amounts. |
| `attach-receipt` | D | Write | Upload a receipt image file and link it to a bank transaction. Preview shows filename and target transaction. |

All write tools implement the `preview`/`confirmed` pattern (default `preview=true`, `confirmed=false`).

MCP tools registered in `App\Mcp\Servers\QvtServer::$tools` under a new `Banking` section.

New tools must have corresponding PHPUnit feature tests (per MCP Server Maintenance Rules in AGENTS.md).

---

## Trade Price Confidentiality Check

| Feature | Risk | Mitigation |
|---------|------|------------|
| Transaction import | None — bank amounts are what customers paid, not trade prices | No trade data flows through banking layer |
| Reconciliation | None — matches bank amounts (fiat) to order payments (fiat) | Both sides are customer-facing amounts |
| Receipt capture | Low — supplier invoices may show trade prices | Receipt storage is staff-only admin area; MCP tools guarded by `admin` role |

All banking components are staff-only, behind `auth` + `verified` + `role:admin` middleware.

---

## Implementation Sequence

| # | Phase | Step | Description |
|---|-------|------|-------------|
| 1 | A | A1 | Create `BankingProvider` interface |
| 2 | A | A2 | Create `MonzoAdapter` + `BankingProviderManager` |
| 3 | A | A4 | Create `config/banking.php` |
| 4 | A | A5 | Create `BankAccount` model + migration |
| 5 | B | B1 | Create `BankTransaction` model + migration |
| 6 | C | C1 | Add `bank_transaction_id` to `payments` migration |
| 7 | B | B2 | Implement `MonzoAdapter::listTransactions()` |
| 8 | B | B3 | Create `TransactionImportService` |
| 9 | B | B6–B7 | Create `banking:import` command + schedule |
| 10 | B | B4–B5 | Create Monzo webhook route + controller |
| 11 | B | B8–B9 | Create `TransactionList` + `TransactionShow` Livewire components |
| 12 | B | B10–B13 | Create MCP transaction tools |
| 13 | C | C3–C4 | Create `ReconciliationService` + auto-match logic |
| 14 | C | C5–C6 | Create `ReconciliationView` Livewire component |
| 15 | C | C7–C9 | Create MCP reconciliation tools |
| 16 | D | D1 | Create `receipts` table + `Receipt` model |
| 17 | D | D2–D4 | Receipt upload UI + Monzo attachment sync |
| 18 | All | — | Feature tests for all Livewire components |
| 19 | All | — | Feature tests for all MCP tools |
| 20 | All | — | Run pint |

---

## Testing Strategy

| Layer | Coverage |
|-------|----------|
| **MonzoAdapter** | Unit test HTTP mock for each endpoint; token refresh logic; error handling (429, 5xx) |
| **TransactionImportService** | Feature test: deduplication, category mapping, pending skip, is_load skip |
| **MonzoWebhookController** | Feature test: valid payload creates record, invalid payload returns 400, duplicate payload skips |
| **ReconciliationService** | Feature test: auto-match happy path, no-match, ambiguous match (multiple candidates) |
| **Livewire components** | Feature test: render, filtering, pagination, category update, manual reconciliation link |
| **MCP tools** | Per existing pattern: preview returns correct data with no DB changes, confirmed executes, validation errors return clear messages, role gating |

---

## Out of Scope

| Feature | Reason |
|---------|--------|
| Purchase Order Reconciliation (Idea #4) | Requires purchase order system to exist first |
| AI Tax Classification (Idea #5) | Needs transaction history to build training data |
| Cash Flow Dashboard (Idea #6) | Depends on mature transaction history (~3+ months) |
| Pot-based Tax Reserve (Idea #7) | Monzo-specific; low urgency |
| Feed Item Notifications (Idea #8) | Monzo-specific; nice-to-have |
| Adding a second banking provider | Architecture supports it but no immediate need |
| Stripe / card payment reconciliation | Separate concern — Monzo is for bank transactions, not payment gateway |

---

## AGENTS.md Updates Required

After implementation, update AGENTS.md with:

1. **Banking Integration section** — listing the bank-agnostic architecture, Monzo adapter, and available MCP tools
2. **New directory conventions** — `app/Banking/` structure with Contracts, Adapters, Models, Services, Livewire, Mcp
3. **MCP Server Maintenance entry** — new Banking tool category in `QvtServer::$tools`
4. **Environment config** — `MONZO_CLIENT_ID`, `MONZO_CLIENT_SECRET`, `MONZO_REDIRECT_URI`

---

*End of plan.*
