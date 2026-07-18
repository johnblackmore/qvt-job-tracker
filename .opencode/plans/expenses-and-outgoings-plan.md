# Expenses & Outgoings Tracking Plan

**Status:** Draft — ready for review  
**Date:** 2026-07-18  
**Author:** AI Planning Agent

---

## 1. Problem Statement

There is currently **no system for tracking money going out of the business**. The existing banking integration imports transactions and reconciles them against **incoming** customer payments, but there is no way to:

- Record supplier orders / purchase invoices
- Track general business expenses (tools, fuel, insurance, etc.)
- Upload and store receipts and invoices as evidence
- Split a single invoice into business vs. personal portions (e.g., Amazon order with mixed items)
- Link a supplier purchase across multiple customer orders
- Reconcile bank debit transactions against expenses
- Export expense data to accounting platforms (QuickBooks, Xero)

---

## 2. Requirements

### Functional Requirements

| # | Requirement | Priority |
|---|-------------|----------|
| 1 | Record supplier invoices and purchase orders with line-level detail | P0 |
| 2 | Upload and store invoice/receipt PDFs and images per expense | P0 |
| 3 | Split an invoice/expense into multiple line items, each independently categorised | P0 |
| 4 | Mark line items as `business` or `personal` (partial expense support) | P0 |
| 5 | Link expense line items to multiple customer orders (allocation across jobs) | P0 |
| 6 | Link expense line items to quote line items | P1 |
| 7 | Reconcile bank debit transactions to expenses (link/unlink) | P0 |
| 8 | Track payment status of each expense (unpaid, paid, overdue) | P0 |
| 9 | Manual expense entry (credit card purchases not in bank feed) | P0 |
| 10 | AI Expenses Assistant — upload PDF/image → AI extracts data → pre-fills form | P1 |
| 11 | Export data in accounting-platform-compatible format (CSV for QuickBooks/Xero) | P1 |
| 12 | Reporting: total outgoings by category, by supplier, by month | P2 |

### Non-Functional Requirements

- All UI must follow the existing Design System (`DESIGN.md`)
- MCP parity — every CRUD operation must have a corresponding MCP tool
- Preview/confirmed pattern on all write tools
- Trade-price confidentiality rules apply where expenses reference product-supplier data
- Files stored locally, with future option for S3
- Compatible with future customer portal (Phase 2)

---

## 3. Data Model

### 3.1 Tables Overview

```
expense_categories          → Categorisation hierarchy (stock, equipment, travel, etc.)
supplier_orders             → Master supplier invoice/order record
supplier_order_line_items   → Line-level breakdown of a supplier order
expenses                    → General business expense records (non-supplier)
expense_line_items          → Line-level breakdown of an expense
allocations                 → Polymorphic links: line_item → order/quote
expense_documents           → File uploads (invoices, receipts)
```

### 3.2 Table Details

#### `expense_categories`
Seeded from existing banking categories + customisable via admin UI.

```sql
id                  BIGINT PK
name                VARCHAR(255)           -- "Stock", "Equipment", "Travel", etc.
slug                VARCHAR(100) UNIQUE
description         TEXT                   NULLABLE
parent_id           BIGINT FK NULLABLE     -- Self-referencing hierarchy
is_active           BOOLEAN DEFAULT TRUE
sort_order          INTEGER DEFAULT 0
created_at / updated_at
```

**Initial seed data** (matches existing `BankTransaction.expense_category` values):
`stock`, `equipment`, `travel`, `fuel`, `subsistence`, `utilities`, `professional_fees`, `insurance`, `other`

#### `supplier_orders`
Represents an order/invoice from a supplier (stock purchases).

```sql
id                          BIGINT PK
reference_number            VARCHAR(50) UNIQUE     -- Auto-generated: PO-YYYYMMDD-XXXX
supplier_id                 BIGINT FK → suppliers  -- NULLABLE for ad-hoc
order_date                  DATE                   -- Date order was placed
invoice_date                DATE                   NULLABLE
invoice_number              VARCHAR(100)           NULLABLE (supplier's invoice ref)
due_date                    DATE                   NULLABLE (payment terms)
subtotal                    DECIMAL(10,2)
vat_total                   DECIMAL(10,2)
total_amount                DECIMAL(10,2)
currency                    CHAR(3) DEFAULT 'GBP'
status                      VARCHAR(20)            -- draft | ordered | received | partially_received | paid | cancelled
payment_method              VARCHAR(30)            NULLABLE (bank_transfer, credit_card, cash, other)
payment_reference           VARCHAR(255)           NULLABLE
paid_at                     DATETIME               NULLABLE
bank_transaction_id         BIGINT FK → bank_transactions NULLABLE (when the whole order matches one txn)
notes                       TEXT                   NULLABLE
created_by_user_id          BIGINT FK → users
metadata                    JSON                   NULLABLE (flexible future fields)
created_at / updated_at / deleted_at (soft)
```

**Indexes:**
- `supplier_id`
- `status`
- `bank_transaction_id`
- `invoice_number`

#### `supplier_order_line_items`
Individual line items on a supplier order. Each can be linked to products in the catalogue or entered ad-hoc.

```sql
id                          BIGINT PK
supplier_order_id           BIGINT FK → supplier_orders  ON DELETE CASCADE
line_type                   VARCHAR(20)                  -- product | service | expense | personal
product_id                  BIGINT FK → products         NULLABLE
product_supplier_id         BIGINT FK → product_supplier  NULLABLE (pivot record)
supplier_sku                VARCHAR(100)                 NULLABLE
description                 TEXT
quantity                    DECIMAL(10,3) DEFAULT 1
unit_amount                 DECIMAL(10,4)                -- Unit price from supplier
vat_rate                    DECIMAL(5,4)                 -- 0.20, 0.05, 0.00
vat_amount                  DECIMAL(10,2)
line_total                  DECIMAL(10,2)                -- qty × unit_amount + vat
line_type_category          VARCHAR(30)                  -- stock | equipment | travel | fuel | subsistence | utilities | professional_fees | insurance | other
notes                       TEXT                         NULLABLE
created_at / updated_at / deleted_at (soft)
```

**Why `line_type` exists:** Setting a line item to `personal` excludes it from business expense reporting, allows splitting mixed orders (e.g. Amazon with 1 business + 2 personal items). Only `business` items flow into accounting exports.

**Indexes:**
- `supplier_order_id`
- `product_id`
- `line_type`

#### `expenses`
General business expenses (receipt from B&Q, fuel receipt, insurance payment, etc.) that are NOT supplier stock orders.

```sql
id                          BIGINT PK
reference_number            VARCHAR(50) UNIQUE          -- EXP-YYYYMMDD-XXXX
expense_category_id         BIGINT FK → expense_categories
description                 TEXT
merchant_name               VARCHAR(255)                NULLABLE
total_amount                DECIMAL(10,2)
vat_total                   DECIMAL(10,2) DEFAULT 0
expense_date                DATE
payment_method              VARCHAR(30)                 NULLABLE (bank_transfer, credit_card, cash, other)
payment_reference           VARCHAR(255)                NULLABLE
paid_at                     DATETIME                    NULLABLE
status                      VARCHAR(20)                 -- draft | approved | paid | cancelled
bank_transaction_id         BIGINT FK → bank_transactions NULLABLE
notes                       TEXT                        NULLABLE
created_by_user_id          BIGINT FK → users
metadata                    JSON                        NULLABLE
created_at / updated_at / deleted_at (soft)
```

**Note:** `expenses` is intentionally **simpler** than `supplier_orders`. Supplier orders have product links, supplier references, and line items that can be allocated to customer orders. General expenses are typically single-line entries with just a receipt.

#### `expense_line_items`
Breakdown of a general expense (for splitting costs). Optional — a simple expense with no line items has `total_amount` as the single entry.

```sql
id                          BIGINT PK
expense_id                  BIGINT FK → expenses ON DELETE CASCADE
description                 TEXT
line_type                   VARCHAR(20)                  -- business | personal
amount                      DECIMAL(10,2)
vat_rate                    DECIMAL(5,4) DEFAULT 0.20
vat_amount                  DECIMAL(10,2) DEFAULT 0
line_type_category          VARCHAR(30)                  -- expense category slug
notes                       TEXT                         NULLABLE
created_at / updated_at
```

#### `allocations`
Polymorphic pivot linking line items from supplier orders (and possibly expenses) to customer orders or quotes. This enables "this solar panel from this supplier order went to customer order #42".

```sql
id                              BIGINT PK
allocatable_from_type           VARCHAR(30)              -- supplier_order_line_item | expense_line_item
allocatable_from_id             BIGINT                   -- FK to the above
allocatable_to_type             VARCHAR(30)              -- order | quote | quote_line_item
allocatable_to_id               BIGINT                   -- FK to the above
amount                          DECIMAL(10,2)            -- Portion of the line item allocated (supports partial)
notes                           TEXT                     NULLABLE
created_at / updated_at
```

**Indexes:**
- `(allocatable_from_type, allocatable_from_id)`
- `(allocatable_to_type, allocatable_to_id)`

**Use cases:**
- A single `supplier_order_line_item` (e.g. "Victron MPPT 100/30") allocated 100% to `order #1`
- A bulk cable purchase from a supplier allocated 50% to `order #1`, 50% to `order #2`
- An Amazon order with cable, fuse, and personal items: cable → `order #1`, fuse → `order #2`, personal → not allocated

#### `expense_documents`
File uploads for invoices and receipts. Polymorphic so we can attach to supplier orders, expenses, or directly to line items.

```sql
id                          BIGINT PK
documentable_type           VARCHAR(30)              -- supplier_order | expense | supplier_order_line_item
documentable_id             BIGINT
file_path                   VARCHAR(500)
original_filename           VARCHAR(255)
mime_type                   VARCHAR(100)
file_size                   INTEGER
document_type               VARCHAR(30)              -- invoice | receipt | statement | other
ai_extraction_id            BIGINT FK → ai_extractions NULLABLE
notes                       TEXT                     NULLABLE
created_at / updated_at
```

**Indexes:**
- `(documentable_type, documentable_id)`

---

## 4. Architecture

### 4.1 Directory Structure — New Files

```
app/
  Models/
    ExpenseCategory.php
    SupplierOrder.php
    SupplierOrderLineItem.php
    Expense.php
    ExpenseLineItem.php
    Allocation.php
    ExpenseDocument.php

  Livewire/
    Expenses/
      SupplierOrderList.php          -- List all supplier orders
      SupplierOrderForm.php          -- Create/edit supplier order + line items
      SupplierOrderShow.php          -- View supplier order with allocations
      ExpenseList.php                -- List business expenses
      ExpenseForm.php                -- Create/edit expense
      ExpenseShow.php                -- View expense
      AllocationPanel.php            -- Drag-and-drop allocation to orders/quotes
      AiExtractionPanel.php          -- AI extraction component (upload + review)

    AiAssistants/
      ExpensesAssistantDetail.php    -- Stats dashboard for expenses AI assistant

  Mcp/
    Tools/
      Expenses/
        CreateSupplierOrderTool.php
        GetSupplierOrderTool.php
        ListSupplierOrdersTool.php
        AddSupplierOrderLineItemTool.php
        UpdateSupplierOrderStatusTool.php
        CreateExpenseTool.php
        GetExpenseTool.php
        ListExpensesTool.php
        UploadDocumentTool.php
        AllocateLineItemTool.php
        ReconcileExpenseTool.php
        ExportExpensesTool.php
        AiExtractExpenseTool.php

  Banking/
    Services/
      ExpenseReconciliationService.php   -- Extends reconciliation to expenses

routes/
  expenses.php

database/
  migrations/
    2026_07_18_000001_create_expense_categories_table.php
    2026_07_18_000002_create_supplier_orders_table.php
    2026_07_18_000003_create_supplier_order_line_items_table.php
    2026_07_18_000004_create_expenses_table.php
    2026_07_18_000005_create_expense_line_items_table.php
    2026_07_18_000006_create_allocations_table.php
    2026_07_18_000007_create_expense_documents_table.php
    2026_07_18_000008_seed_expense_categories.php
```

### 4.2 Existing Files to Modify

| File | Change |
|------|--------|
| `app/Mcp/Servers/QvtServer.php` | Register all new Expense tools + resources |
| `routes/web.php` | Add `require __DIR__.'/expenses.php'` |
| `resources/views/layouts/app.blade.php` | Add "Expenses" nav item (between Orders and Banking) |
| `app/Banking/Services/ReconciliationService.php` | Extend to include expense matching |
| `app/Mcp/Prompts/` | Add `ExpensesAssistantPrompt.php` |

---

## 5. UI / Livewire Components

### 5.1 Navigation

New main nav item between **Orders** and **Banking**:

```blade
<a href="{{ route('expenses.supplier-orders.index') }}" ...>
    <x-lucide-receipt class="w-5 h-5 shrink-0" />
    Expenses
</a>
```

Clicking "Expenses" opens a sub-nav or landing page (TBD — see design decisions below).

### 5.2 Component Breakdown

#### `SupplierOrderList` (index)
- Table of all supplier orders
- Filters: status, supplier, date range
- Actions: create new, view, delete
- Badge for payment status

#### `SupplierOrderForm`
- Two-panel layout:
  - Left: Supplier details, dates, totals
  - Right: Line items table (add/remove/edit rows)
- Each line item row: type selector (product/service/personal), description, qty, unit price, VAT
- Product lookup with autocomplete (existing catalogue)
- Real-time total calculation
- File upload area for invoice
- AI "Extract from Invoice" button (if uploading)

#### `SupplierOrderShow`
- Read-only view of the supplier order
- Line items table with allocation status per row
- "Allocate to Orders" button per line item
- Document preview/download
- Bank reconciliation status
- Payment recording

#### `ExpenseList` (index)
- Table of expenses
- Filters: category, date range, status
- Quick-add button

#### `ExpenseForm`
- Simple form: merchant, date, amount, category, payment method
- Optional receipt upload
- Optional line-item breakdown

#### `ExpenseShow`
- View expense detail with receipt preview
- Bank reconciliation status
- Link/unlink from bank transaction

#### `AllocationPanel`
- Shown within `SupplierOrderShow` when clicking "Allocate"
- Left: line item details
- Right: searchable list of customer orders with quantity/amount input
- Shows remaining unallocated balance

#### `AiExtractionPanel`
- File upload zone (drag & drop PDF/image)
- Loading state while AI processes
- Extracted data review form (edit before saving)
- Save button creates the record

### 5.3 AI Expenses Assistant

Following the existing pattern:

- **New entry in `AiAssistantsIndex.php`** with stats (extractions count, success rate, tokens used)
- **New Livewire component `ExpensesAssistantDetail.php`** — lists extraction history
- **AI extraction flow:**
  1. User drops a PDF/image onto the upload zone
  2. File sent to backend → stored temporarily
  3. Sent to AI provider (same config system as existing ProductExtractor)
  4. AI extracts: supplier name, invoice number, date, line items, totals, VAT
  5. Returns structured JSON → pre-fills `SupplierOrderForm` or `ExpenseForm`
  6. User reviews, edits, and saves → file stored permanently as `ExpenseDocument`
  7. Extraction logged in `AiExtraction` table (existing model)

**Prompt template** for the extraction assistant:

```
Extract expense/invoice data from this document for the QVT business.
Return structured JSON with:
- supplier_name
- invoice_number
- invoice_date
- due_date (if visible)
- line_items (array of {description, quantity, unit_amount, vat_rate, line_total, line_type: "business"|"personal"})
- subtotal
- vat_total
- total_amount
- currency
- payment_terms (if visible)
```

---

## 6. MCP Tools

### 6.1 Tool Register

| Tool Class | Description | Read/Write |
|------------|-------------|------------|
| `ListSupplierOrdersTool` | List supplier orders with filters | Read |
| `GetSupplierOrderTool` | Get single supplier order with line items | Read |
| `CreateSupplierOrderTool` | Create supplier order + line items | Write (preview) |
| `AddSupplierOrderLineItemTool` | Add line item to existing order | Write (preview) |
| `UpdateSupplierOrderStatusTool` | Change status (ordered/received/paid) | Write (preview) |
| `ListExpensesTool` | List business expenses | Read |
| `GetExpenseTool` | Get single expense with details | Read |
| `CreateExpenseTool` | Create a new business expense | Write (preview) |
| `UploadDocumentTool` | Upload invoice/receipt and link to record | Write |
| `AllocateLineItemTool` | Allocate line item to customer order | Write (preview) |
| `ReconcileExpenseTool` | Link expense/supplier-order to bank transaction | Write (preview) |
| `ExportExpensesTool` | Export expenses in CSV format | Read |
| `AiExtractExpenseTool` | Upload + AI extract expense data | Write (preview) |

### 6.2 Tool Patterns

All write tools follow the established pattern:

```php
#[IsIdempotent]
#[Description('...')]
class CreateSupplierOrderTool extends Tool
{
    public function schema(JsonSchema $schema): array { ... }
    public function outputSchema(JsonSchema $schema): array { ... }
    public function shouldRegister(Request $request): bool { ... }
    public function handle(Request $request): Response|ResponseFactory
    {
        // preview / confirmed pattern
        // Returns: status, message, url, data
    }
}
```

### 6.3 Resource Registration

New resources for MCP context:

| Resource | URI |
|----------|-----|
| `SupplierOrderDetailsResource` | `qvt://supplier-orders/{id}` |
| `ExpenseDetailsResource` | `qvt://expenses/{id}` |

### 6.4 Prompt Registration

| Prompt | Description |
|--------|-------------|
| `ExpensesAssistantPrompt` | Instructions for the AI assistant when helping with expense tasks |

---

## 7. Banking Reconciliation

### 7.1 Current State
`BankTransaction` can be reconciled to `Payment` (incoming customer payments) via `matched_payment_id`. The `ReconciliationService` handles auto-match and manual match.

### 7.2 Extension for Expenses
Add a **polymorphic reconciliation** approach:

Option A — **Add `reconcilable_type` / `reconcilable_id` to `bank_transactions`:**
Replace the single `matched_payment_id` FK with a polymorphic relationship. This is the cleanest approach but requires a migration on an existing table.

```sql
ALTER TABLE bank_transactions ADD COLUMN reconcilable_type VARCHAR(30) NULL;
ALTER TABLE bank_transactions ADD COLUMN reconcilable_id BIGINT NULL;
ALTER TABLE bank_transactions DROP COLUMN matched_payment_id;  -- or keep for backwards compat
```

Actually, to avoid breaking existing functionality, use a separate link table:

Option B — **New `reconciliation_links` table:**
```sql
reconciliation_links
- id
- bank_transaction_id FK UNIQUE   -- One txn per link (not splittable across multiple expenses)
- reconcilable_type VARCHAR(30)     -- payment | supplier_order | expense
- reconcilable_id BIGINT
- amount DECIMAL(10,2)              -- Matched portion (may differ from total, e.g. credit-on-account)
- matched_at DATETIME
- matched_by_user_id FK
- notes TEXT NULLABLE
- created_at
```

A single bank transaction can link to at most one reconcilable record (enforced by `UNIQUE` on `bank_transaction_id`). The `amount` field allows partial reconciliation — e.g., a £500 supplier order matched to a £300 bank debit with the remaining £200 tracked as credit-on-account.

**Recommendation:** Option B (new pivot table) for maximum flexibility, with a migration that moves existing `matched_payment_id` data into the new table.

### 7.3 Reconciliation UI
The existing `ReconciliationView` Livewire component (split-panel) should be extended to also show unmatched supplier orders and expenses alongside unmatched payments.

---

## 8. Accounting Export

### 8.1 Export Format

CSV export compatible with QuickBooks and Xero:

| Field | Source | Notes |
|-------|--------|-------|
| `Date` | `expense_date` / `order_date` | |
| `Reference` | `reference_number` | |
| `Supplier/Customer` | `supplier.name` / `merchant_name` | |
| `Description` | `description` | |
| `Account Code` | `line_type_category` → mapped | Map our categories to accounting COA |
| `Net Amount` | `subtotal` | Excluding VAT |
| `VAT Amount` | `vat_total` | |
| `Gross Amount` | `total_amount` | |
| `VAT Rate` | `vat_rate` | |
| `VAT Code` | Mapped from VAT rate | e.g., T20, T5, T0 |
| `Order Ref` | Customer order reference | If allocated |
| `Transaction Type` | `supplier_order` / `expense` | |

### 8.2 Category Mapping

Create a configurable mapping from QVT expense categories to accounting platform account codes:

```php
// App/Settings/AccountingMappingSettings
class AccountingMappingSettings extends Settings
{
    public array $category_account_codes = [
        'stock' => '5000',        // Cost of Goods Sold
        'equipment' => '5200',    // Tools & Equipment
        'travel' => '5300',       // Travel
        'fuel' => '5301',         // Fuel
        'subsistence' => '5400',  // Subsistence
        'utilities' => '5500',    // Utilities
        'professional_fees' => '5600',
        'insurance' => '5700',
        'other' => '5900',        // Miscellaneous
    ];
    
    public static function group(): string { return 'accounting'; }
}
```

---

## 9. Implementation Phases

### Phase 1: Core Data Model & CRUD (Week 1)

| Task | Effort | Dependencies |
|------|--------|-------------|
| Create migration for `expense_categories` + seed data | Small | None |
| Create `ExpenseCategory` model | Small | Migration done |
| Create migration for `supplier_orders` + model | Medium | Suppliers table exists |
| Create migration for `supplier_order_line_items` + model | Medium | supplier_orders + products tables exist |
| Create migration for `expenses` + model | Small | expense_categories table exists |
| Create migration for `expense_line_items` + model | Small | expenses table exists |
| Create migration for `expense_documents` + model | Small | ai_extractions table exists |
| Create migration for `allocations` + model | Medium | Orders exist |
| Create `routes/expenses.php` | Small | Controllers exist |
| Register in `web.php` | Tiny | Routes file done |
| Add nav item to sidebar | Tiny | Layout file |
| Build `SupplierOrderList` Livewire component | Medium | Models exist |
| Build `SupplierOrderForm` Livewire component | Large | Models + routes exist |
| Build `SupplierOrderShow` Livewire component | Medium | Models + routes exist |
| Build `ExpenseList` Livewire component | Small | Models exist |
| Build `ExpenseForm` Livewire component | Medium | Models exist |
| Build `ExpenseShow` Livewire component | Small | Models exist |
| File upload/download handling | Medium | Models exist |
| Add storage disk config for documents | Tiny | None |

### Phase 2: Allocations & Reconciliation (Week 2)

| Task | Effort | Dependencies |
|------|--------|-------------|
| Create `reconciliation_links` table + migration | Medium | Phase 1 complete |
| Update `BankTransaction` model with polymorphic relation | Medium | Migration done |
| Extend `ReconciliationService` for expenses | Medium | BankTransaction updated |
| Build `AllocationPanel` Livewire component | Large | Allocations model + routes exist |
| Build `AllocateLineItemTool` MCP tool | Medium | Allocations model exists |
| Build `ReconcileExpenseTool` MCP tool | Medium | Reconciliation links exist |
| Update `ReconciliationView` to show unmatched expenses | Medium | Reconciliation service extended |
| Wire up bank reconciliation in ExpenseShow/SupplierOrderShow | Medium | All components exist |

### Phase 3: AI Expenses Assistant (Week 3)

| Task | Effort | Dependencies |
|------|--------|-------------|
| Build prompt template for expense extraction | Small | None |
| Build `AiExtractionPanel` Livewire component | Large | Phase 1 complete |
| Build `ExpensesAssistantDetail` Livewire component | Medium | AiExtraction model exists |
| Register assistant in `AiAssistantsIndex` | Small | Component exists |
| Add `AiConfig` entry for expenses assistant | Small | Existing pattern |
| Build `AiExtractExpenseTool` MCP tool | Medium | All models exist |
| Connect extraction output to form pre-fill | Medium | AiExtractionPanel exists |
| Store uploaded file as `ExpenseDocument` on save | Small | ExpenseDocument model exists |

### Phase 4: MCP Tools & Accounting Export (Week 3-4)

| Task | Effort | Dependencies |
|------|--------|-------------|
| Build `CreateSupplierOrderTool` | Medium | Phase 1 complete |
| Build `GetSupplierOrderTool` | Small | Phase 1 complete |
| Build `ListSupplierOrdersTool` | Small | Phase 1 complete |
| Build `CreateExpenseTool` | Medium | Phase 1 complete |
| Build `GetExpenseTool` | Small | Phase 1 complete |
| Build `ListExpensesTool` | Small | Phase 1 complete |
| Build `UploadDocumentTool` | Small | Phase 1 complete |
| Build `ExportExpensesTool` | Medium | Phase 1-2 complete |
| Create `AccountingMappingSettings` | Small | None |
| Build CSV export controller | Medium | Phase 1 complete |
| Register all tools in `QvtServer.php` | Small | All tools exist |
| Create resource classes | Small | Phase 1 complete |

---

## 10. Design Decisions

### 10.1 Supplier Orders vs. Expenses — Two Models or One?

**Decision: Two separate tables.**

- **Supplier orders** (`supplier_orders` + `supplier_order_line_items`) have product catalogue links, supplier references, and complex allocation needs. They represent stock purchases.
- **Expenses** (`expenses` + `expense_line_items`) are simpler, fee-based outgoings (fuel, insurance, etc.) without product links.

This separation makes the schema clearer, queries simpler, and reporting more accurate. A unified view ("all outgoings") can be provided via a Livewire dashboard that queries both tables.

### 10.2 File Storage Strategy

**Decision: Local `expenses/` directory on the `local` disk (`storage/app/private/expenses/`).**

- Existing Receipt model already uses this pattern (`storage_path('app/'.$this->file_path)`)
- Files served via a download route (same pattern as `routes/banking.php` receipt download)
- Future: easily swap to S3 by changing the disk config

### 10.3 Navigation — Sub-nav or Separate?

**Decision: Main nav item "Expenses" with a landing page that shows both tabs (Supplier Orders + General Expenses).**

Two tabs on the landing page, similar to how products works. No sub-nav clutter. The landing page has two quick-action buttons: "New Supplier Order" and "New Expense".

### 10.4 Polymorphic vs. Junction Table for Allocations

**Decision: Single `allocations` table with polymorphic `morphs`.**

This is the most flexible approach. We can allocate line items to orders, quotes, or even quote line items without creating a new pivot table each time. The `amount` field on the pivot supports partial allocations.

### 10.5 Bank Reconciliation — Separate Pivot Table

**Decision: New `reconciliation_links` table rather than polymorphic columns on `bank_transactions`.**

- Avoids altering the existing `BankTransaction` table schema significantly
- Preserves existing `matched_payment_id` field and its current logic
- Each bank transaction can be linked to exactly one *expense* or *payment*, enforced at application level (not DB constraint, to allow migration)
- Existing reconciliation service continues unchanged; new service handles expense reconciliation

### 10.6 AI Assistant — Reuse or New?

**Decision: Reuse the existing AiExtraction model and pattern (same as Product Extractor).**

- The Product Extractor's flow (upload → AI → review → save) is a perfect template
- Create a new `assistant_name` value: `expenses-extractor`
- New prompt template specific to expense/invoice extraction
- Reuse the existing `AiModelConfig` selection UI
- The extracted data schema differs: supplier info, line items, VAT breakdown, totals

---

## 11. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Data model too complex for MVP | Medium | Start with `supplier_orders` + `expenses` only; add allocations in Phase 2 |
| AI extraction accuracy on poor-quality scans | Medium | Always show extracted data for user review before save; allow manual override |
| Accounting export format changes | Low | Use CSV with configurable column mapping; write a service class that can have format adapters |
| Breaking existing bank reconciliation | High | New `reconciliation_links` table doesn't touch existing `matched_payment_id` logic |
| File storage requirements grow | Low | Abstract storage behind Laravel filesystem; swap to S3 later |

---

## 12. Resolved Decisions

| # | Question | Decision |
|---|----------|----------|
| 1 | Status workflow? | Simple string (`draft`, `ordered`, `received`, `partially_received`, `paid`, `cancelled`) — no state machine |
| 2 | Purchase Order workflow? | Not needed initially. Direct invoice recording only. |
| 3 | Allocation tracking? | Track by **fixed amount**, not percentage. Uses `amount` column on pivot. |
| 4 | Partial reconciliation? | **Yes** — `reconciliation_links.amount` captures the matched portion. One bank debit maps to at most one expense (not split across multiple). Supports credits-on-account / extra discounts on the expense side. |
| 5 | Personal line type? | Keep as `line_type = 'personal'` on line items. Not a boolean. |
| 6 | Export frequency? | Manual CSV export. Automated integration with accounting APIs is future. |
