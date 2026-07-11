# AGENTS.md — Quantock Van Tech Job Tracker

## Project Overview

This is the **Quantock Van Tech Job Tracker** — a Laravel 13 + Livewire + daisyUI application for managing a campervan electrical installation business.

**Website:** https://quantockvantech.com/
**Business:** Specialist supply & fit campervan electrical systems in West Somerset (solar, lithium batteries, charging, upgrades).

This is a **staff-only admin application** (Phase 1). Customers do not log in or access their accounts in the initial build.

---

## Technology Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| Framework | Laravel 13.x | Latest stable |
| Frontend | Livewire 3.x | Full-page and component Livewire |
| CSS | Tailwind CSS 4.x | Utility-first |
| UI Components | daisyUI 5.x | Pre-built Tailwind components |
| Icons | Lucide | Via `blade-ui-kit/blade-icons` |
| Auth | Laravel Breeze (Livewire) | Login, registration, password reset |
| Permissions | spatie/laravel-permission | Roles: `admin`, `installer` |
| Mail | Postmark | Transactional email delivery |
| Database | MySQL 8.x | Local dev: `qvt_job_tracker`, user `root` / `root` |
| Rich Text | Trix | For email template editing |

---

## Design System

### Mode
- **Light mode ONLY** for the initial build.
- All daisyUI components should use light theme variants (no `dark:` prefixes unless explicitly requested).

### Colour Palette
```
Primary:    emerald-600 (#059669)  — Buttons, links, active states, focus rings
Background: white / slate-50       — Page and card backgrounds
Text:       slate-700 / slate-900  — Body text and headings
Accent:     blue-600               — Informational elements
Warning:    amber-500              — Status badges, warnings
Danger:     red-600                — Destructive actions, errors
Success:    emerald-500            — Success states
```

### Typography
- Font family: `Inter`, `system-ui`, `-apple-system`, `sans-serif`
- Headings: `font-semibold`, `tracking-tight`
- Body: `text-slate-700`, `leading-relaxed`

### Spacing & Radius
- Cards: `rounded-xl`, `shadow-sm`, `border border-slate-200`
- Buttons: `rounded-lg`
- Inputs: `rounded-lg`, focus ring `ring-emerald-500`
- Tables: Compact padding, striped rows, `rounded-xl` container

### Layout
- **Sidebar navigation** (collapsible on mobile) + main content area.
- Dashboard home with stat cards in a grid.
- Content pages use max-width containers with generous padding.

### Iconography
- Use **Lucide** icons exclusively.
- Icon size: `w-5 h-5` for inline, `w-6 h-6` for navigation.

---

## Critical Business Rules

### 1. Trade Price Confidentiality
**NEVER expose trade prices to customers.**

- Customer-facing quotes, PDFs, and emails show **retail prices + labour only**.
- Trade prices are stored internally for margin tracking and reporting.
- Staff views can show both retail and trade prices side-by-side for reference.
- When in doubt, show retail.

### 2. Quote Builder
- **Pick-from-catalogue** is the preferred method for adding line items.
- Category tabs or filtering for product discovery.
- Click product → select supplier variant (default to `is_preferred`) → add to quote.
- **Ad-hoc items** allowed for custom labour or non-catalogue parts.
- Real-time total calculation (retail subtotal + labour = grand total).

### 3. Sample Quotes
- Stored as templates with no customer link.
- Cloning creates a real quote linked to a specific customer.
- Template line items store product IDs; cloned quotes pull current prices.

---

## File & Naming Conventions

### Livewire Components
- Location: `app/Livewire/`
- Namespace: `App\Livewire`
- Naming: PascalCase, descriptive (e.g., `CustomerList`, `QuoteBuilder`)
- Views: `resources/views/livewire/{kebab-case}.blade.php`

### Models
- Location: `app/Models/`
- Relationships defined explicitly with type hints where possible.
- Use `$fillable` or `$guarded` consistently (prefer `$fillable`).

### Migrations
- Timestamped, descriptive names.
- Foreign keys with `constrained()->onDelete('cascade')` or `onDelete('set null')` as appropriate.

### Seeders
- `AdminUserSeeder` — creates default staff admin on fresh install.
- `RoleSeeder` — creates `admin` and `installer` roles.

---

## Environment Configuration

### Database (Local Development)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qvt_job_tracker
DB_USERNAME=root
DB_PASSWORD=root
```

### Postmark Mail
```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=efdb29c6-a079-416c-bf89-b0cbd0e3d6ea
```

### App
```env
APP_NAME="QVT Job Tracker"
APP_ENV=local
APP_URL=http://localhost
```

---

## MCP Servers

This project uses the following MCP configuration (stored in `.opencode/opencode.json`):

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

When building UI components, consult the daisyUI GitMCP for component patterns and examples.

---

## Testing

- Use **Pest** for testing (Laravel's preferred test runner).
- Test critical paths: quote creation, trade price hiding, email sending.
- Feature tests for Livewire components.

---

## Dependencies to Install

### Composer
```bash
composer require spatie/laravel-permission
composer require wildbit/postmark-php
```

### NPM
```bash
npm install -D daisyui@latest
npm install -D @tailwindcss/typography
```

### Laravel Breeze
```bash
composer require laravel/breeze --dev
php artisan breeze:install livewire
```

---

## Communication Tone

The application UI should communicate with the same professionalism as the QVT website:
- Clear, concise labels and instructions.
- Helpful empty states (e.g., "No enquiries yet. Add your first customer enquiry to get started.")
- Action-oriented button text ("Send Quote", "Add Product", "Schedule Installation").
- Avoid jargon in customer-facing copy; technical detail is fine in staff-only views.

---

## Future Phase 2 (Customer Portal)

Not part of the initial build, but planned:
- Magic link login (no passwords)
- View quotes and orders
- Pay deposits online
- Submit support questions
- View installation schedule

When building Phase 1, keep data structures compatible with Phase 2 requirements (e.g., quotes have `reference_number` suitable for external sharing).
