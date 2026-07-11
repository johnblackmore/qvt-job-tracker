# Quantock Van Tech — Job Tracker Project Plan

## Overview

A Laravel 13 + Livewire + daisyUI application for managing Quantock Van Tech's business operations: customers, enquiries, quotes, orders, products, and professional email communications.

**Business:** Quantock Van Tech (QVT) — Specialist campervan electrical installations in West Somerset. Website: https://quantockvantech.com/

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13.x (latest stable) |
| Frontend | Livewire 3.x + Blade |
| CSS Framework | Tailwind CSS 4.x + daisyUI 5.x |
| Icons | Lucide (via Blade icons) |
| Database | MySQL 8.x (local: `qvt_job_tracker`, user `root` / `root`) |
| Auth | Laravel Breeze (Livewire) + Spatie Permission |
| Mail | Postmark (token configured in `.env`) |
| Rich Text | Trix editor for emails |
| Local Dev | `php artisan serve` / Laravel Valet |

---

## Database Schema

### Core Tables

```
users
  └── roles (via spatie/laravel-permission: admin, installer)

customers
  ├── id, name, email, phone, address, notes, created_at, updated_at

vehicles
  ├── customer_id, make, model, registration, year, type, notes

enquiries
  ├── customer_id (nullable), source (web/phone/email/referral), status, subject, message, responded_at, staff_user_id

suppliers
  ├── id, name, contact_name, email, phone, website, address, notes, is_active

product_categories
  ├── name, slug, description

products
  ├── sku, name, description, category_id, retail_price, stock_qty, is_active, notes

product_supplier (pivot/link table)
  ├── product_id, supplier_id, trade_price, supplier_product_url, supplier_sku, is_preferred, lead_time_days, notes

quotes
  ├── customer_id, reference_number, status (draft|sent|accepted|declined|expired),
  │   total_retail, total_trade, labour_total, grand_total, notes,
  │   valid_until, sent_at, accepted_at, declined_at, converted_order_id, staff_user_id

quote_line_items
  ├── quote_id, line_type (product|labour|ad_hoc), product_id (nullable), product_supplier_id (nullable),
  │   description, quantity, unit_retail_price, unit_trade_price, line_total_retail, line_total_trade, notes

sample_quotes (templates)
  ├── name, description, line_items_json, is_active, notes

orders
  ├── customer_id, quote_id (nullable), reference_number, status (pending|deposit_paid|scheduled|in_progress|completed|cancelled),
  │   total_amount, deposit_required, deposit_paid, balance_due, scheduled_date,
  │   completed_at, staff_user_id, notes

email_templates
  ├── name, slug, subject, body_html, body_text, variables_json, is_active

emails_sent
  ├── customer_id, to_email, subject, body_html, template_id (nullable),
  │   quote_id (nullable), order_id (nullable), sent_at, postmark_message_id, status
```

### Key Business Rules

- **Trade prices are NEVER shown to customers.** Only retail prices + labour appear in customer-facing quotes, PDFs, and emails.
- Products can have multiple suppliers via the `product_supplier` pivot table.
- When adding a product to a quote, default to the `is_preferred` supplier.
- Sample quotes store product IDs + prices; cloned quotes pull current prices from the library.

---

## Implementation Phases

### Phase 0: Foundation (COMPLETE)
1. Install Laravel 13 via Composer
2. Configure `.env` (MySQL, Postmark, app name)
3. Install Laravel Breeze (Livewire variant)
4. Install spatie/laravel-permission
5. Install Livewire, daisyUI, Tailwind
6. Configure Postmark mail driver
7. Create `AGENTS.md`
8. Run migrations, create default admin user seeder
9. Configure MCP (daisyUI GitMCP)

### Phase 1: Staff Admin & Auth (COMPLETE)
1. Set up roles (`admin`, `installer`)
2. Build dashboard overview (stats cards)
3. Staff profile management
4. QVT-branded login page with emerald styling
5. Sidebar navigation (collapsible on mobile)

### Phase 2: Customer Management (COMPLETE)
1. Customers CRUD (Livewire table + form)
2. Vehicle management (nested under customer)
3. Enquiries CRUD + link to customer
4. Customer search and filtering
5. Live dashboard stats for customers and enquiries

### Phase 3: Suppliers & Products Library (COMPLETE)
1. Suppliers CRUD — name, contact, email, phone, website, address, notes, active toggle
2. Product categories CRUD — name, slug, description
3. Products CRUD with retail pricing, stock tracking, SKU, category, active toggle
4. Product-supplier links with trade pricing — multiple suppliers per product, preferred supplier flag, lead time, supplier SKU/URL
5. Stock quantity tracking
6. Sidebar navigation updated with active states for all sections

### Phase 4: Sample Quotes & Quote Builder (COMPLETE)
1. Sample quotes (templates) CRUD — create, edit, list, delete
2. Clone sample quote to real quote (via QuoteBuilder mount)
3. Line item storage in JSON (SampleQuote model)
4. Quote builder: pick from catalogue + ad-hoc + labour items
5. Quote status workflow (draft → sent → accepted → declined → expired)
6. Real quotes CRUD — list, create, edit, show views
7. Trade/retail totals calculation with internal-only trade cost display
8. Sidebar navigation activated for Quotes section

### Phase 5: PDF & Emails (COMPLETE)
1. PDF generation for quotes (retail prices only) — `barryvdh/laravel-dompdf` installed, A4 PDF with QVT branding
2. PDF download and preview routes via `QuotePdfController`
3. Email templates CRUD — name, slug, subject, HTML/plain text body, variables JSON, active toggle
4. `EmailTemplate` model with `render()` method for variable substitution
5. `QuoteEmailService` — generates PDF attachment, builds email from template or default view, sends via Postmark
6. Quote show page with Preview PDF, Download PDF, Send Quote, and Edit actions
7. Send Quote modal — select template, add custom message, send to customer email
8. Auto-updates quote status from `draft` to `sent` when emailed
9. `emails_sent` table tracks all outgoing emails with status and Postmark metadata
10. Sidebar navigation activated for Email Templates section

### Phase 6: Orders (COMPLETE)
1. Orders migration and `Order` model with customer/quote/staff relationships
2. Order status workflow: pending → deposit_paid → scheduled → in_progress → completed → cancelled
3. Order CRUD: list (searchable, status-filtered, deposit progress bars), create, edit, show
4. Deposit tracking: deposit_required, deposit_paid, balance_due, deposit percent progress bar
5. Installation scheduling via `scheduled_date` field
6. Auto-set `completed_at` timestamp when status changed to completed
7. Convert accepted quote to order — one-click from quote show page pre-fills customer, total, and 30% deposit
8. Sidebar navigation activated for Orders section
9. Order show page displays financial summary, schedule card, linked quote, and customer info

### Phase 7: Polish
1. Responsive design pass
2. Search across all records
3. Notifications/toasts for actions
4. Test suite (Pest)

### Phase 2 (Future): Customer Portal
- Magic link login (no passwords)
- View quotes and orders
- Pay deposits
- Submit support questions

---

## Design Guidelines

### Brand Identity
Quantock Van Tech is a professional electrical installation specialist. The UI must reflect trustworthiness, professionalism, and clean technical precision.

### Colour Palette
- **Mode:** Light mode primary
- **Primary:** Emerald/teal green (`#059669` or Tailwind `emerald-600`) — matches eco/solar brand
- **Background:** Clean white (`#ffffff`) with subtle off-white (`#f8fafc`) for card sections
- **Text:** Slate grey (`#334155` for body, `#0f172a` for headings)
- **Accents:** Blue for informational elements, amber for warnings, red for destructive actions

### UI Patterns
- **Cards:** White with soft shadow (`shadow-sm`), rounded corners (`rounded-xl`), subtle borders
- **Typography:** Clean sans-serif (Inter or system-ui), generous line-height
- **Layout:** Sidebar navigation + main content area
- **Buttons:** Solid green primary, ghost/outline secondary, soft grey tertiary
- **Forms:** Clean inputs with emerald focus rings, labels above inputs
- **Tables:** Striped rows, compact but readable, action buttons in row
- **Status badges:** Colour-coded (Draft = grey, Sent = blue, Accepted = emerald, Declined = red, Expired = amber)

### Key Rules for Agents
- ALWAYS use light mode components from daisyUI
- NEVER expose trade prices in any customer-facing view, email, or PDF
- ALWAYS match the clean, professional aesthetic of the QVT website
- Prefer `rounded-xl` for cards, `rounded-lg` for buttons and inputs
- Use Lucide icons consistently

---

## MCP Configuration

```json
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "daisyui-gitmcp": {
      "type": "remote",
      "url": "https://gitmcp.io/saadeghi/daisyui",
      "enabled": true
    }
  }
}
```

---

## Email Templates (Planned)

1. **Quote Sent** — includes quote reference, expiry date, summary
2. **Order Confirmation** — deposit details, schedule info
3. **Installation Scheduled** — date, time, preparation notes
4. **Installation Complete** — handover summary, warranty info
5. **Follow-up / Generic** — rich text composer for ad-hoc
6. **Enquiry Response** — reply to customer enquiry

---

## Postmark Configuration

- Driver: `postmark`
- Token: `efdb29c6-a079-416c-bf89-b0cbd0e3d6ea`
- Used for: transactional emails (quotes, orders, enquiries), future inbound email handling

---

## Development Notes

- Local database: `qvt_job_tracker` (MySQL, root/root)
- Admin user seeded on install
- All customer data is internal-only; no customer portal in Phase 1
- Quote builder must support pick-from-catalogue + ad-hoc line items
- Responsive design required for mobile quote creation on-site
