# Order System Improvements Plan

## Overview

Four interconnected improvements to the Order system:

1. Auto-generate reference numbers for all new orders
2. "Convert to Order" action from accepted quotes (both list and show views)
3. Smarter deposit calculation (max of 50% of total, or cost of products)
4. Payments infrastructure — track individual payments against orders

---

## Feature 1: Auto-generated Reference Number

### What

When creating a new order (either from scratch or from a quote), pre-fill `reference_number` with `ORD-YYYYMMDD-XXXX` format but keep it editable.

### Implementation

**`app/Livewire/Orders/OrderForm.php`** — `mount()` method:

Move reference generation out of the `elseif ($quoteId)` block so it runs for ALL new orders (not just quote-originated ones).

```php
// In mount(), after the if/elseif blocks:
if (! $orderId) {
    $this->reference_number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));
}
```

### Changes

| File | Change |
|------|--------|
| `app/Livewire/Orders/OrderForm.php` | Move ref generation to run for all create modes |

---

## Feature 2: Convert to Order from Accepted Quotes

### What

Add a "Convert to Order" action on both the **quote list** and **quote show** page when a quote has `status === 'accepted'`. This navigates to `orders/create/from-quote/{quoteId}`.

Also track conversion status — don't allow converting a quote that already has a `converted_order_id`.

### Implementation

**a) `app/Livewire/Quotes/QuoteList.php`**

Add a `convertToOrder(int $id)` method:

```php
public function convertToOrder(int $id): void
{
    $quote = Quote::findOrFail($id);
    $this->redirect(route('orders.create-from-quote', $quote->id), navigate: true);
}
```

**b) `resources/views/livewire/quotes/quote-list.blade.php`**

In the actions column, add a convert button after the edit button, shown only when status is `accepted` and `converted_order_id` is null:

```blade
@if($quote->status === 'accepted' && ! $quote->converted_order_id)
    <button wire:click="convertToOrder({{ $quote->id }})" class="p-1.5 rounded-lg text-slate-400 hover:text-teal hover:bg-teal/10 transition-colors" title="Convert to order">
        <x-lucide-clipboard-plus class="w-4 h-4" />
    </button>
@endif
```

**c) `app/Livewire/Quotes/QuoteShow.php`**

Already has a `convertToOrder()` method that redirects. But we need to add a check: if the quote already has `converted_order_id`, show a "View Order" link instead.

In the view (`quote-show.blade.php`), change the condition:

```blade
@if($quote->status === 'accepted' && ! $quote->converted_order_id)
    <button wire:click="convertToOrder" class="...">Convert to Order</button>
@elseif($quote->converted_order_id)
    <a href="{{ route('orders.show', $quote->converted_order_id) }}" wire:navigate class="...">View Order</a>
@endif
```

**d) `app/Livewire/Orders/OrderForm.php` — `save()` method**

When creating an order from a quote, update `quote.converted_order_id` (currently only the MCP tool does this):

```php
if ($this->quote_id && ! $this->order) {
    Quote::where('id', $this->quote_id)->update(['converted_order_id' => $order->id]);
}
```

### Changes

| File | Change |
|------|--------|
| `app/Livewire/Quotes/QuoteList.php` | Add `convertToOrder()` method |
| `resources/views/livewire/quotes/quote-list.blade.php` | Add convert button for accepted, unconverted quotes |
| `resources/views/livewire/quotes/quote-show.blade.php` | Add "View Order" link when already converted |
| `resources/views/livewire/quotes/quote-show.blade.php:44-49` | Replace simple convert button with conditional logic |
| `app/Livewire/Orders/OrderForm.php` | Update `quote.converted_order_id` on order creation |

---

## Feature 3: Smarter Deposit Calculation

### What

When creating an order from a quote, calculate deposit as: **max(50% of grand_total, cost of products)**.

Product cost = `grand_total - labour_total` (labour lines excluded).

For orders not from a quote, keep the existing behaviour (user enters manually).

### Implementation

**`app/Livewire/Orders/OrderForm.php` — `mount()` method, `elseif ($quoteId)` block:**

```php
$productCost = $quote->grand_total - $quote->labour_total;
$halfTotal = $quote->grand_total * 0.5;
$deposit = max($halfTotal, $productCost);

$this->deposit_required = (string) round($deposit, 2);
```

**`app/Mcp/Tools/CreateOrderTool.php` — `handle()` method, line 97:**

Apply the same calculation logic so the MCP tool matches the web UI.

### Changes

| File | Change |
|------|--------|
| `app/Livewire/Orders/OrderForm.php:51` | Replace `30%` with `max(50%, products_cost)` |
| `app/Mcp/Tools/CreateOrderTool.php:97` | Same deposit calculation update |

---

## Feature 4: Payments Table & Recording

### What

Create a `payments` table to record individual payments against orders. This replaces the flat `deposit_paid` approach with a proper transaction log.

### Implementation

#### a) Migration — `create_payments_table.php`

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->onDelete('cascade');
    $table->decimal('amount', 10, 2);
    $table->string('method'); // bank_transfer, card, cash, other
    $table->string('reference')->nullable();
    $table->timestamp('paid_at');
    $table->text('notes')->nullable();
    $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->onDelete('set null');
    $table->timestamps();
});
```

#### b) Model — `app/Models/Payment.php`

```php
class Payment extends Model
{
    protected $fillable = ['order_id', 'amount', 'method', 'reference', 'paid_at', 'notes', 'recorded_by_user_id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo { ... }
    public function recordedBy(): BelongsTo { ... }
}
```

#### c) Order Model Updates — `app/Models/Order.php`

Add relationship and computed attributes:

```php
public function payments(): HasMany
{
    return $this->hasMany(Payment::class);
}

// Keep deposit_paid as a computed sum of payments
public function getDepositPaidAttribute(): float
{
    return (float) $this->payments()->sum('amount');
}

// Keep balance_due as computed
public function getBalanceDueAttribute(): float
{
    return max(0, $this->total_amount - $this->deposit_paid);
}

// Add a helper
public function getPaymentCountAttribute(): int
{
    return $this->payments()->count();
}
```

**IMPORTANT:** Change `deposit_paid` and `balance_due` from stored columns to computed attributes. Remove them from `$fillable` and `$casts`. The existing migration stays — the columns still exist in the DB but are no longer written to (they'll be computed on read). This avoids a breaking migration.

#### d) Order Form — Payment Recording

**`app/Livewire/Orders/OrderForm.php`**

Add payment recording sub-form:
- Add `$newPaymentAmount`, `$newPaymentMethod`, `$newPaymentReference`, `$newPaymentDate` properties
- Add `recordPayment()` method that creates a Payment record
- Display existing payments in a table within the form

The OrderForm already has `$deposit_paid` — this can remain as a manual field for initial setup (migration path) or be hidden in favour of the payments list. For simplicity, **keep `$deposit_paid` as a manual field for now** (allows setting an initial deposit without going through the payment form), and add the payment recording as an additional way to track payments.

**`resources/views/livewire/orders/order-form.blade.php`**

Add a "Payments" section after the financial fields:
- Table of recorded payments (amount, method, reference, date, notes)
- "Record Payment" button that expands inline fields
- Use a Livewire `$listeners` / `$dispatch` pattern or inline Alpine toggle

#### e) Order Show — Payment History

**`app/Livewire/Orders/OrderShow.php`**

Eager-load `payments` relationship.

**`resources/views/livewire/orders/order-show.blade.php`**

Add a "Payments" card below the Financial Summary:
- List of recorded payments with date, amount, method, reference
- Total paid vs required progress bar (already exists)

### Changes

| # | File | Change |
|---|------|--------|
| 1 | `database/migrations/XXXX_XX_XX_create_payments_table.php` | New migration |
| 2 | `app/Models/Payment.php` | New model |
| 3 | `app/Models/Order.php` | Add `payments()` relationship, computed `deposit_paid`/`balance_due` |
| 4 | `app/Livewire/Orders/OrderForm.php` | Add payment recording properties + method |
| 5 | `resources/views/livewire/orders/order-form.blade.php` | Add payments section |
| 6 | `app/Livewire/Orders/OrderShow.php` | Eager-load payments |
| 7 | `resources/views/livewire/orders/order-show.blade.php` | Add payment history display |

---

## MCP Tool Updates

Per the MCP Server Maintenance Rules (from AGENTS.md), any new write action needs a corresponding MCP tool.

| Tool | Change |
|------|--------|
| `CreateOrderTool.php` | Update deposit calc (Feature 3), pass `balance_due` as computed |
| `RecordPaymentTool.php` | **New tool** — record a payment against an order (preview/confirmed pattern) |

---

## Implementation Order

| Step | Description | Depends On |
|------|-------------|-----------|
| 1 | Auto-generate reference for create mode | Nothing |
| 2 | Convert to Order button on quote list + show | Step 1 (for clean flow) |
| 3 | Update `quote.converted_order_id` in web UI save | Step 2 |
| 4 | New deposit calculation | Step 2 (uses quote totals) |
| 5 | Payments migration + model | Nothing |
| 6 | Payment recording on order form + show | Step 5 |
| 7 | MCP `RecordPaymentTool` | Step 5 |
| 8 | Update `CreateOrderTool` deposit calc | Step 4 |

---

## Trade Price Confidentiality Check

- Payments table stores only the `amount` paid — no trade prices
- Quote conversion only uses `grand_total`, `labour_total` — no trade data
- Safe for all staff roles

---

## Design Notes

- Payment method uses a `<select>` with options: Bank Transfer, Card, Cash, Other
- Payment dates default to `now()` but are editable
- The existing `deposit_paid` field stays as a manual override for migration/simplicity
- Payment history in Order Show uses a simple table with consistent slate/copper styling
