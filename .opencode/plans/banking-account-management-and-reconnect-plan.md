# Banking Account Management & Reconnect Plan

## Problem

- The sole connected Monzo account lost its OAuth tokens in a data loss event
- No UI exists to see which bank accounts are connected
- No reconnect flow — re-linking requires creating a new account (duplicate)

## What We're Building

### 1. Account List Page (`/admin/banking/accounts`)

New Livewire component showing all `BankAccount` records as cards:

- Name, provider, type, active status
- Current balance (cached via `BalanceService`) + last fetched time
- Status badge: "Connected" (tokens present) vs "Reconnection Needed" (tokens missing/expired)
- Actions per card: **Reconnect**, **Refresh Balance**, **Disconnect/Remove**
- "Connect New Account" button at top

**Access:** Linked from the transactions page header (next to "Link Another Account")

### 2. Reconnect Flow

Reuses the existing OAuth flow but re-links to the existing `BankAccount`:

1. User clicks **Reconnect** on an account card → `MonzoOAuthController::redirectReconnect($account)` stores `reconnect_account_id` + `reconnect_provider_account_id` in session, redirects to Monzo
2. Normal OAuth flow: `callback()` creates a pending account with new tokens, redirects to `ApproveConnection`
3. User approves in Monzo app, clicks "I've approved"
4. `retry()` fetches Monzo accounts list, auto-matches by `provider_account_id`:
   - **Match found:** Updates existing account's tokens from pending account's metadata, deletes pending account → redirects to transactions with success
   - **No match:** Falls back to `SelectAccount` for manual picking
5. Manual picking on `SelectAccount` also checks reconnect context — updates existing account, deletes pending

### 3. Balance Refresh on Account Page

Each account card has a "Refresh Balance" button that calls `BalanceService::refreshBalance()` and shows a success/error notification. No full-page reload.

## Files

### Create
- `app/Livewire/Banking/AccountList.php`
- `resources/views/livewire/banking/account-list.blade.php`

### Modify
- `routes/banking.php` — add `accounts` + `reconnect/{account}` routes
- `app/Banking/Controllers/MonzoOAuthController.php` — add `redirectReconnect()`, modify `retry()`
- `app/Livewire/Banking/SelectAccount.php` — handle reconnect context in `linkAccount()`
- `resources/views/livewire/banking/transaction-list.blade.php` — add "Manage Accounts" link
