# VAT & Cost Price Tracking Plan

**Status:** Ready for implementation  
**Date:** 2026-07-16  
**Decisions:** See [Resolved Questions](#resolved-questions) below

---

## Problem Statement

Different suppliers use different pricing conventions — some quote trade prices **ex-VAT**, others quote **inc-VAT**. Currently there's no way to distinguish which convention a given `trade_price` was entered under.

QVT is **not VAT registered**, so:
- **No VAT is charged on sales** — retail/sell prices are the prices customers pay, full stop.
- **VAT paid on purchases is a real cost** — it forms part of the true cost of materials, which matters for tax returns (cost of goods sold reduces taxable profit).

The goal: know the **true cost** of every item by correctly handling VAT on trade prices, without ever adding VAT to customer-facing prices.

---

## Key Business Rules

1. **Sell prices never include VAT** — QVT is not VAT registered; the "retail price" is the final amount the customer pays.
2. **Trade prices may be ex-VAT or inc-VAT** depending on the supplier. This must be configurable per product-supplier link.
3. **True cost = what QVT actually pays.** If trade price is ex-VAT, true cost = trade + VAT. If trade price is inc-VAT, true cost = trade price (VAT already baked in).
4. **Most purchases are standard-rated (20% VAT).** Reduced rate (5%) and zero rate (0%) exist for edge cases (energy-saving materials, books, etc).
5. **Trade prices remain confidential.** True cost (including VAT) is internal-only, just like `total_trade` today.
6. **Quotes snapshot cost at time of creation** — same pattern as retail/trade price snapshotting already in use.

---

## Technical Design

### 1. VAT Rate Configuration

**New Settings class:** `App\Settings\VatSettings`

Uses `spatie/laravel-settings` (already installed, pattern established by `AiAssistantConfigSettings`).

```php
class VatSettings extends Settings
{
    public float $standard_rate = 0.20;   // 20%
    public float $reduced_rate = 0.05;    // 5%
    public float $zero_rate = 0.00;       // 0%

    public static function group(): string
    {
        return 'vat';
    }
}
```

A **VAT rate enum/contract** in `app/Enums/VatRateType.php`:

```php
enum VatRateType: string
{
    case Standard = 'standard';
    case Reduced = 'reduced';
    case Zero = 'zero';
}
```

A **helper service** or trait for calculating VAT amounts and resolving rates from the settings class.

### 2. Database Schema Changes

#### 2a. `product_supplier` pivot — VAT metadata on trade price

Add to `product_supplier`:

| Column | Type | Default | Purpose |
|---|---|---|---|
| `trade_price_includes_vat` | `boolean` | `false` | Is the `trade_price` already inclusive of VAT? |
| `vat_rate_type` | `varchar(20)` | `'standard'` | Which VAT rate applies (standard/reduced/zero) |

- `trade_price_includes_vat = false` → trade price is ex-VAT → true cost = trade * (1 + VAT%)
- `trade_price_includes_vat = true` → trade price already includes VAT → true cost = trade
- `vat_rate_type` determines which percentage to use (from `VatSettings`)

> **Migration:** new file `database/migrations/2026_07_16_XXXXXX_add_vat_fields_to_product_supplier.php`

#### 2b. `suppliers` — default pricing convention

Add to `suppliers`:

| Column | Type | Default | Purpose |
|---|---|---|---|
| `default_trade_price_includes_vat` | `boolean` | `false` | Default when adding new product links |

This pre-fills the `trade_price_includes_vat` checkbox when linking a product to this supplier, saving clicks for suppliers that consistently use one convention.

> **Migration:** new file `database/migrations/2026_07_16_XXXXXX_add_trade_price_includes_vat_to_suppliers.php`

#### 2c. `quote_line_items` — snapshot the effective cost

Add to `quote_line_items`:

| Column | Type | Default | Purpose |
|---|---|---|---|
| `vat_rate` | `decimal(5,4)` | `0.2000` | VAT rate applied at time of quoting (e.g. 0.2000 for 20%) |
| `unit_cost_price` | `decimal(10,2)` | `0` | Effective unit cost (trade + VAT if applicable) |
| `line_total_cost` | `decimal(10,2)` | `0` | `quantity * unit_cost_price` |

- `unit_trade_price` keeps its current meaning: the raw trade price as entered.
- `unit_cost_price` is the computed true cost: `trade * (1 + vat_rate)` if ex-VAT, or just `trade` if inc-VAT.
- These are snapshots — captured at quote creation time and never recalculated.

> **Migration:** new file `database/migrations/2026_07_16_XXXXXX_add_cost_fields_to_quote_line_items.php`

#### 2d. `quotes` — total true cost

Add to `quotes`:

| Column | Type | Default | Purpose |
|---|---|---|---|
| `total_cost` | `decimal(10,2)` | `0` | Sum of all `line_total_cost` (true cost including VAT paid) |

- `total_trade` keeps its current meaning (sum of raw trade prices).
- `total_cost` is the true cost including VAT — this is the number used for tax return material cost calculations.

> **Migration:** new file `database/migrations/2026_07_16_XXXXXX_add_total_cost_to_quotes.php`

### 3. Model & Code Changes

#### 3a. Models

**`Supplier`**
- Add `default_trade_price_includes_vat` to `$fillable` and `$casts`

**`Product`** — `suppliers()` relationship:
- Add `trade_price_includes_vat` and `vat_rate_type` to pivot fields
- Add computed `costPrice` accessor that calculates true cost from trade price + VAT

**`QuoteLineItem`**
- Add `vat_rate`, `unit_cost_price`, `line_total_cost` to `$fillable` and `$casts`

**`Quote`**
- Add `total_cost` to `$fillable` and `$casts`

#### 3b. `QuoteBuilder` Livewire Component

- In `addProductLine()`: carry forward `trade_price_includes_vat` and `vat_rate_type` from the pivot
- Add `trade_price_includes_vat` and `vat_rate_type` to each line item array
- In `getTotalsProperty()`: compute `totalCost` (sum of effective line costs)
- In `save()`: compute and store `unit_cost_price`, `line_total_cost`, `vat_rate` for each line item; store `total_cost` on the quote

#### 3c. `ProductForm` Livewire Component

- In the supplier link form, add:
  - A `trade_price_includes_vat` checkbox/radio per supplier link
  - A `vat_rate_type` dropdown (standard/reduced/zero) per supplier link
- Validation updates for the new fields
- Sync the new fields when saving product's supplier relationships

#### 3d. `SupplierForm` / Supplier management (if one exists)

- Add `default_trade_price_includes_vat` field

### 4. View Changes

#### 4a. Product form (`resources/views/livewire/products/product-form.blade.php`)
- Add `trade_price_includes_vat` toggle and `vat_rate_type` select in each supplier link block
- Pre-fill `trade_price_includes_vat` from supplier default when adding new links

#### 4b. Quote builder (`resources/views/livewire/quotes/quote-builder.blade.php`)
- In the "Trade cost (internal only)" totals section, show **true cost** alongside raw trade
- The line items form does NOT need VAT fields visible (cost is pre-calculated from product-supplier data)

#### 4c. PDF quote (`resources/views/pdf/quote.blade.php`)
- Remove the misleading footer line: "All prices include VAT where applicable"
- Replace with: "All prices are the final amount payable. Quantock Van Tech is not VAT registered."

#### 4d. Quote detail/show views (if any exist)
- Show `total_cost` in internal staff views alongside `total_trade`

### 5. VAT Calculation Logic

```
function calculateTrueCost(tradePrice, includesVat, vatRate): float {
    if (includesVat) {
        return tradePrice;           // VAT already in the price
    }
    return tradePrice * (1 + vatRate);  // Add VAT
}
```

Where `vatRate` is resolved from `VatSettings` based on `vat_rate_type`:
- `'standard'` → `0.20`
- `'reduced'` → `0.05`  
- `'zero'` → `0.00`

### 6. Data Migration — Amazon Supplier

Existing data needs updating:
- **Amazon supplier:** set `default_trade_price_includes_vat = true`
- **All `product_supplier` pivot rows for Amazon:** set `trade_price_includes_vat = true`

This can be done as a migration with raw SQL or a seeder. Since it's a one-time data fix, a migration is appropriate.

> **Migration:** new file `database/migrations/2026_07_16_XXXXXX_set_amazon_supplier_inc_vat.php`

This migration will:
1. Find the supplier named `'Amazon'` (or containing `'amazon'` case-insensitively)
2. Update `suppliers.default_trade_price_includes_vat = true` on that row
3. Update `product_supplier.trade_price_includes_vat = true` for all pivot rows linked to that supplier

### 7. VAT Settings Admin Page

Following the established pattern from `AiAssistantConfigSettings`:

**Livewire Component:** `app/Livewire/Admin/VatSettings.php`
- Full-page component
- Form with number inputs for `standard_rate`, `reduced_rate`, `zero_rate` (displayed as percentages 20%, 5%, 0%)
- `mount()` reads current values from `app(VatSettings::class)`
- `save()` validates and persists

**Blade View:** `resources/views/livewire/admin/vat-settings.blade.php`
- Card-style form with three rate inputs
- Help text explaining each rate's typical use in UK

**Route:** New file `routes/admin-settings.php`, required by `web.php`
- `GET /admin/vat-settings` → `VatSettings::class` → `admin.vat-settings`
- Protected by `auth`, `verified`, `role:admin` middleware

**Sidebar:** Add "VAT Settings" link in the Admin section of `layouts/app.blade.php`

### 8. Seeding & Factories

- **Settings seeder:** Seed default VAT rates (standard 0.20, reduced 0.05, zero 0.00) into the `settings` table via `VatSettings` on first run
- Update `SupplierFactory` to randomize `default_trade_price_includes_vat` (mostly false)
- Update product seeders to populate new pivot fields

---

## Implementation Sequence

| # | Step | Files affected |
|---|---|---|
| 1 | Create `VatRateType` enum | `app/Enums/VatRateType.php` |
| 2 | Create `VatSettings` settings class + settings migration | `app/Settings/VatSettings.php` + `database/settings/` |
| 3 | Seeder for default VAT rate values | `VatSettingsSeeder` (or add to `DatabaseSeeder`) |
| 4 | Migration: add fields to `suppliers` | new migration |
| 5 | Migration: add fields to `product_supplier` | new migration |
| 6 | Migration: add fields to `quote_line_items` | new migration |
| 7 | Migration: add fields to `quotes` | new migration |
| 8 | Migration: set Amazon inc-VAT | data migration |
| 9 | Update `Supplier` model | `$fillable`, `$casts`, pivot fields |
| 10 | Update `Product` model | pivot fields, cost accessor |
| 11 | Update `QuoteLineItem` model | `$fillable`, `$casts` |
| 12 | Update `Quote` model | `$fillable`, `$casts` |
| 13 | Create VAT helper service | `app/Services/VatService.php` |
| 14 | Update `ProductForm` | new fields in supplier links array, validation, sync |
| 15 | Update `QuoteBuilder` | cost calculation in totals + save |
| 16 | Update `product-form.blade.php` | VAT fields in supplier link section |
| 17 | Update `quote-builder.blade.php` | true cost in totals section |
| 18 | Update `quote.blade.php` (PDF) | fix footer text |
| 19 | Update quote detail views | show total_cost |
| 20 | Create VAT settings Livewire component | `app/Livewire/Admin/VatSettings.php` |
| 21 | Create VAT settings blade view | `resources/views/livewire/admin/vat-settings.blade.php` |
| 22 | Add VAT settings route | route file + `web.php` |
| 23 | Add sidebar navigation link | `layouts/app.blade.php` |
| 24 | Create feature tests | `tests/Feature/Livewire/Admin/VatSettingsTest.php`, update existing tests |
| 25 | Run database migrations | `php artisan migrate` |
| 26 | Run tests | `php artisan test --compact` |
| 27 | Run pint | `vendor/bin/pint --format agent` |

---

## Resolved Questions

| # | Question | Decision |
|---|---|---|
| 1 | Default `trade_price_includes_vat`? | **`false` (ex-VAT)** — matches B2B electrical trade norms |
| 2 | Per-supplier default? | **Yes**, `suppliers.default_trade_price_includes_vat` — Amazon pre-fills inc-VAT, all others pre-fill ex-VAT |
| 3 | Backfill existing data? | **Yes** — set `default_trade_price_includes_vat = true` on the Amazon supplier; all others default to `false` (ex-VAT). Also update existing `product_supplier` pivot rows for Amazon products |
| 4 | VAT rate admin UI? | **Yes, editable** — Livewire settings page at `/admin/vat-settings`, following the same pattern as `AiAssistantConfigSettings` (form page + spatie/laravel-settings persistence) |
| 5 | Cost breakdown per line in quote builder? | **Aggregate only** — show `total_cost` in the totals panel. Per-line cost is derivable from trade price + VAT fields already visible in line items

---

## Trade-offs & Rationale

| Decision | Rationale |
|---|---|
| VAT rates in Settings, not hardcoded | Future-proof — rates can change, and the app may need different rates later |
| `vat_rate` snapshot on line items | Follows existing pattern of snapshotting prices at quote time; prevents recalculation confusion if rates change |
| Separate `unit_trade_price` and `unit_cost_price` | Preserves the raw entered trade price for auditability; cost is derived |
| `trade_price_includes_vat` on pivot, not supplier | Different products from same supplier could theoretically have different VAT treatments (though rare, it's more correct) |
| Per-supplier default | Saves data entry time for suppliers that consistently use one convention |

---

## Out of Scope

- VAT returns / MTD submissions (QVT is not VAT registered)
- Charging VAT on sales (not applicable)
- Customer VAT exemption tracking (not applicable since no VAT is charged)
- Multiple VAT rates per line item (rare edge case; add later if needed)
- Reverse charge / zero-rating for exports (not needed)
