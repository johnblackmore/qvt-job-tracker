# Quote Cloning Feature — Implementation Plan

## Overview

Allow staff to clone an existing quote into a new draft quote, preserving line items and customer but resetting reference, status, and valid_until date. The user can then make edits before saving.

### What "clone" means

| Field | Source Quote | Cloned Quote |
|-------|-------------|--------------|
| `customer_id` | Carried over | Same customer |
| `reference_number` | Original | Auto-generated (blank → `Q-YYYYMMDD-RAND`) |
| `status` | Original | `draft` |
| `valid_until` | Original | Reset to +30 days from now |
| `notes` | Carried over | Carried over |
| `enquiry_id` | Carried over | Carried over |
| `staff_user_id` | Original staff | Current authenticated user |
| Line items & prices | Copied verbatim | Copied verbatim (no re-fetch from catalogue) |

### Entry Points

1. **Quote list screen** — "Clone" icon button per quote row
2. **Quote view screen** — "Clone" header action button
3. **MCP tool** — `CloneQuoteTool` (preview/confirmed pattern, admin only)

---

## Files to Modify

### 1. Route — `routes/quotes.php`

```php
Route::get('quotes/create/from-existing/{quoteId}', QuoteBuilder::class)
    ->name('quotes.create-from-existing');
```

### 2. Livewire Component — `app/Livewire/Quotes/QuoteBuilder.php`

**New property:**
```php
public ?int $sourceQuoteId = null;
```

**Modified `mount()`** — add `$sourceQuoteId` parameter, insert logic after `$sampleQuoteId` block:

1. Load `Quote::with('lineItems')->findOrFail($sourceQuoteId)`
2. Set `$this->customer_id` from source
3. Set `$this->notes` from source
4. Set `$this->enquiryId` from source
5. Leave `$this->reference_number = ''` (auto-generated)
6. Leave `$this->status = 'draft'`
7. Leave `$this->valid_until` as +30 days default
8. Populate `$this->lineItems` from source line items — copy all price fields verbatim

**`save()`** — no changes needed: `$this->quote` is null, so it creates a new Quote.

### 3. Builder View — `resources/views/livewire/quotes/quote-builder.blade.php`

Update title and subtitle conditionals to handle the `$sourceQuoteId` state.

### 4. Quote List View — `resources/views/livewire/quotes/quote-list.blade.php`

Add clone button (`x-lucide-copy`) in mobile card and desktop table action rows.

### 5. Quote Show View — `resources/views/livewire/quotes/quote-show.blade.php`

Add clone button in header action row.

### 6. MCP Tool — `app/Mcp/Tools/CloneQuoteTool.php`

`#[IsIdempotent]`, preview/confirmed pattern. Schema: `quote_id` (required), `customer_id` (optional), `notes` (nullable), `preview`, `confirmed`. Creates new quote with verbatim line item copies.

### 7. Register in `app/Mcp/Servers/QvtServer.php`

Add import and register in both `$tools` array and `toolClasses()`.

### 8. Update `app/Mcp/Prompts/QuoteAssistantPrompt.php`

Add `clone-quote` to listed tools.

---

## Tests

### Web UI Tests — `tests/Feature/QuoteCloneTest.php`

| Test | What it covers |
|------|---------------|
| `clone_quote_loads_builder_with_copied_data` | Route loads QuoteBuilder with correct fields |
| `clone_quote_resets_reference_status_valid_until` | Builder mount resets the three fields |
| `clone_quote_copies_line_items` | Line items array is populated from source |
| `clone_quote_saves_as_new_quote` | Saving creates a new Quote record (not update) |
| `clone_quote_from_list_screen` | Route accessible from list view |
| `clone_quote_from_show_screen` | Route accessible from show view |
| `clone_quote_preserves_enquiry_link` | `enquiry_id` is carried over |

### MCP Tool Tests — `tests/Feature/Mcp/Tools/CloneQuoteToolTest.php`

| Test | What it covers |
|------|---------------|
| `preview_returns_correct_data` | Preview mode shows source details, no DB changes |
| `execute_creates_cloned_quote` | Confirmed mode creates new quote with copied line items |
| `execute_resets_reference_status_valid_until` | Confirms the three resets |
| `execute_copies_prices_verbatim` | Line item prices match source exactly |
| `validation_error_with_invalid_quote_id` | Returns clear error for bad quote_id |
| `unauthenticated_request_returns_empty` | Auth gate works |
| `optional_customer_override` | Specifying `customer_id` changes the customer |

---

## Execution Order

1. Add route to `routes/quotes.php`
2. Modify `QuoteBuilder::mount()` to handle `sourceQuoteId`
3. Update `quote-builder.blade.php` title
4. Add clone button to `quote-list.blade.php` (mobile + desktop)
5. Add clone button to `quote-show.blade.php`
6. Create `CloneQuoteTool` MCP tool
7. Register tool in `QvtServer.php`
8. Update `QuoteAssistantPrompt.php`
9. Write feature tests
10. Write MCP tool tests
11. Run `vendor/bin/pint --format agent`
12. Run full test suite
