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

### Phase 0: Foundation (IN PROGRESS)
1. Install Laravel 13 via Composer
2. Configure `.env` (MySQL, Postmark, app name)
3. Install Laravel Breeze (Livewire variant)
4. Install spatie/laravel-permission
5. Install Livewire, daisyUI, Tailwind
6. Configure Postmark mail driver
7. Create `AGENTS.md`
8. Run migrations, create default admin user seeder
9. Configure MCP (daisyUI GitMCP)

### Phase 1: Staff Admin & Auth
1. Set up roles (`admin`, `installer`)
2. Build dashboard overview (stats cards)
3. Staff profile management

### Phase 2: Customer Management
1. Customers CRUD (Livewire table + form)
2. Vehicle management (nested under customer)
3. Enquiries CRUD + link to customer
4. Customer search and filtering

### Phase 3: Suppliers & Products Library
1. Suppliers CRUD
2. Product categories CRUD
3. Products CRUD with retail pricing
4. Product-supplier links with trade pricing
5. Stock quantity tracking

### Phase 4: Sample Quotes
1. Sample quotes (templates) CRUD
2. Clone sample quote to real quote
3. Line item storage in JSON

### Phase 5: Quotes & Emails
1. Quote builder: pick from catalogue + ad-hoc items
2. Quote status workflow
3. PDF generation for quotes
4. Email templates CRUD
5. Send quotes to customers via Postmark (retail prices only)

### Phase 6: Orders
1. Convert accepted quote to order
2. Order status workflow
3. Deposit tracking
4. Schedule installation dates

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
