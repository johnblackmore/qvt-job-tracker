# Mobile Optimisation & PWA Support — Technical Scope & Build Plan

## Overview

Four areas of work:

1. **Fix mobile sidebar toggle** — burger icon does nothing (Alpine scope bug)
2. **Dashboard card layout** — stat cards should be 2x2 on mobile
3. **Responsive card layouts for list views** — replace horizontal-scroll tables with cards on mobile
4. **PWA support** — manifest, service worker, icons, meta tags, install prompt
5. **Logo integration** — use the uploaded SVG logo across dashboard, PDFs, PWA icons

---

## 1. Fix Mobile Sidebar Toggle

### Root Cause

`resources/views/layouts/app.blade.php` — the `x-data="{ sidebarOpen: false }"` is defined on the `<aside>` element, but the hamburger `<button>` is a sibling inside a different div. Alpine.js `@click` can only access data from the current element or its ancestors — not siblings.

### Fix

Move `x-data` from the `<aside>` to the outermost `<div class="min-h-screen flex">` wrapper, making `sidebarOpen` accessible to both the sidebar `<aside>` and the hamburger `<button>`.

**Changes:**
- `resources/views/layouts/app.blade.php`:21 — move `x-data="{ sidebarOpen: false }"` to the parent `div.min-h-screen.flex` on line 13
- Also add `@keydown.window.escape="sidebarOpen = false"` on the same parent div

---

## 2. Dashboard Stat Cards — 2×2 Grid on Mobile

**File:** `resources/views/livewire/pages/dashboard.blade.php`:55

### Current

```blade
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
```

On mobile (below 640px): single column stack. Four stat cards take too much vertical space.

### Change

Switch from `grid-cols-1` to `grid-cols-2` for a clean 2×2 layout on the smallest screens.

```blade
<div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
```

No other changes needed — each card is compact enough for half-width.

---

## 3. Responsive Card Layouts for Table List Views

### Approach

For each list view currently using `<table>`, add a **responsive card layout** that:
- Shows the table normally on `md:` screens and above
- Renders each record as a **card** on screens below `md:`
- Uses CSS `hidden md:table-cell` / `block md:hidden` or `hidden md:table` / `block md:hidden` pattern

A cleaner approach: wrap the table in a `hidden md:block` and add a card-based section below in `block md:hidden`. However, since these are Livewire components with different column sets, we should create a **strategy** rather than duplicating the entire data for each view.

### Recommended Implementation Pattern

For each list view, add mobile card markup inside the `@if($records->count() > 0)` block, parallel to the existing table:

```blade
{{-- Mobile card view --}}
<div class="block md:hidden divide-y divide-slate-100">
    @foreach($records as $record)
        <div class="p-4 space-y-3">
            {{-- Card content tailored to each list type --}}
        </div>
    @endforeach
</div>

{{-- Desktop table view --}}
<div class="hidden md:block overflow-x-auto">
    <table>...</table>
</div>
```

### Affected Views (9 total)

| View | File | Key Card Fields |
|------|------|-----------------|
| **Customers** | `livewire/customers/customer-list.blade.php` | Name, email, phone, vehicles/enquiries/quotes counts, actions |
| **Quotes** | `livewire/quotes/quote-list.blade.php` | Reference, customer, status badge, total, date, actions |
| **Orders** | `livewire/orders/order-list.blade.php` | Reference, customer, status badge, total, deposit progress, scheduled date, actions |
| **Products** | `livewire/products/product-list.blade.php` | Name, SKU, category, retail price, stock, suppliers count, actions |
| **Suppliers** | `livewire/suppliers/supplier-list.blade.php` | Name, contact, products count, active/inactive toggle, actions |
| **Sample Quotes** | `livewire/sample-quotes/sample-quote-list.blade.php` | Name, description, line items count, total, active status, actions |
| **Expenses** | `livewire/expenses/expense-list.blade.php` | Reference, merchant, category, date, amount, status, actions |
| **Supplier Orders** | `livewire/expenses/supplier-order-list.blade.php` | Reference, supplier, invoice, order date, total, status, actions |
| **Banking Transactions** | `livewire/banking/transaction-list.blade.php` | Date, description, merchant, amount, category badge, reconciliation status, actions |

### Card Design Pattern (consistent across all lists)

Each mobile card should follow this visual structure:

```
┌──────────────────────────────────┐
│ [Status Badge]   [Actions]       │
│                                   │
│ Primary Info (linked)             │
│ Secondary detail row              │
│                                   │
│ Label: Value    Label: Value      │
└──────────────────────────────────┘
```

- Cards sit in a single column (full width) for readability
- Use the existing colour-coded status badges
- Keep action buttons (edit, delete, etc.) in top-right or bottom-right
- Use `divide-y divide-slate-100` between cards
- Each card has `px-4 py-3` padding, `hover:bg-slate-50`

### Note: Enquiries

The Enquiries list is **already card-based** (not a table), so it doesn't need conversion. It should already work fine on mobile. Review for minor spacing tweaks only.

---

## 4. PWA Support

### 4.1 Install `vite-plugin-pwa`

```bash
npm install -D vite-plugin-pwa
```

### 4.2 Configure `vite.config.js`

Add `vite-plugin-pwa` with the Laravel-specific integration:

```js
import { VitePWA } from 'vite-plugin-pwa';

VitePWA({
    registerType: 'autoUpdate',
    includeAssets: ['favicon.ico', 'images/quantock-van-tech-logo.svg'],
    manifest: {
        name: 'QVT Job Tracker',
        short_name: 'QVT Jobs',
        description: 'Quantock Van Tech — Staff admin for campervan electrical installation business',
        theme_color: '#B45309',
        background_color: '#FAFBFC',
        display: 'standalone',
        orientation: 'portrait-primary',
        start_url: '/dashboard',
        icons: [
            {
                src: 'images/pwa-192x192.png',
                sizes: '192x192',
                type: 'image/png',
            },
            {
                src: 'images/pwa-512x512.png',
                sizes: '512x512',
                type: 'image/png',
            },
        ],
    },
    workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
        runtimeCaching: [
            {
                urlPattern: /^https?:\/\/.*\/api\/.*/i,
                handler: 'NetworkFirst',
                options: {
                    cacheName: 'api-cache',
                    expiration: {
                        maxEntries: 50,
                        maxAgeSeconds: 60 * 60,
                    },
                },
            },
        ],
    },
})
```

### 4.3 Generate PWA Icons from the Logo SVG

**Decision:** Generate programmatically using Node.js `sharp` package.

Install:
```bash
npm install -D sharp
```

Create a generate script or inline the generation in a build step. The SVG logo uses blue/teal (#406882) and orange (#F49948) tones. Icons should render the SVG at the required sizes on a transparent background.

Generate:
- `public/images/pwa-192x192.png`
- `public/images/pwa-512x512.png`
- `public/images/apple-touch-icon.png` (180x180)
- `public/favicon.svg` (optional SVG favicon)

### 4.4 Update Layout `<head>` Meta Tags

In `resources/views/layouts/app.blade.php` and `guest.blade.php`, add:

```html
<meta name="theme-color" content="#B45309" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="apple-mobile-web-app-title" content="QVT Jobs" />
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}" />
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}" />
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
```

### 4.5 Offline Fallback

With `vite-plugin-pwa` and the Workbox configuration above:
- The app shell (HTML, CSS, JS) will be cached on first visit via `globPatterns`
- API calls use `NetworkFirst` strategy — serve from cache if offline, fall back to network
- Navigation requests use a basic network-first or a custom offline page

Consider adding a simple offline page at `resources/views/offline.blade.php` and route it as a fallback if needed.

---

## 5. Logo Integration

The SVG logo exists at `public/images/quantock-van-tech-logo.svg`. It's a detailed full-colour logo (blue/teal background with orange lightning bolt).

### 5.1 Sidebar — Replace inline icon

**File:** `resources/views/layouts/app.blade.php`:22-30

Replace:
```blade
<div class="w-8 h-8 rounded-lg bg-copper flex items-center justify-center">
    <x-lucide-bolt class="w-5 h-5 text-white" />
</div>
<div class="flex flex-col">
    <span class="text-sm font-bold text-slate-900 leading-none">QVT</span>
    <span class="text-[10px] text-slate-500 leading-none mt-0.5">Job Tracker</span>
</div>
```

With:
```blade
<a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
    <img src="{{ asset('images/quantock-van-tech-logo.svg') }}" alt="Quantock Van Tech" class="h-8 w-auto" />
    <div class="flex flex-col">
        <span class="text-sm font-bold text-slate-900 leading-none">QVT</span>
        <span class="text-[10px] text-slate-500 leading-none mt-0.5">Job Tracker</span>
    </div>
</a>
```

The logo is an SVG with `width="1024" height="1024"` and a coloured background rectangle — it will render as a small square icon at `h-8`.

Consider creating a simpler variant or cropping the SVG if needed for the sidebar.

### 5.2 PDF Quote Template

**File:** `resources/views/pdf/quote.blade.php`:19-22

Replace the inline copper square with bolt icon:
```html
<div class="brand-icon">
    <svg width="18" height="18" viewBox="0 0 24 24" ...>
        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </svg>
</div>
```

With an embedded or referenced logo.

**Decision:** Use **base64 embed** — encode the SVG as a data URI in the HTML for reliable PDF rendering without external file dependencies.

```html
<img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('images/quantock-van-tech-logo.svg'))) }}" alt="Quantock Van Tech" style="height: 32px; width: auto;" />
```

The quote PDF also needs the brand name/tagline updated to match the logo.

### 5.3 Guest Layout

**File:** `resources/views/layouts/guest.blade.php`

Currently shows an inline copper `bolt` icon. Replace with the logo SVG for brand consistency.

### 5.4 Application Logo Component

**File:** `resources/views/components/application-logo.blade.php`

This is a hexagon SVG shape used by Breeze (possibly in email templates or registration). Replace or leave based on usage. If used only in the original Breeze scaffolding (not in current app views), it may be safe to leave or remove.

### 5.5 PWA Icons (generated from logo)

See section 4.3 — generate PNG icons from the SVG logo for PWA manifest and apple-touch-icon.

---

## Implementation Order

### Phase A — Quick Fixes (30 min)

1. Fix sidebar Alpine scope bug
2. Dashboard 2×2 grid

### Phase B — Mobile Card Views (2-3 hours per view, ~8-10 hours total)

3. Convert each of the 9 table-based list views to support card layout on mobile
   - Start with the most-used: Customers, Quotes, Orders
   - Then: Enquiries (minor tweaks), Products, Suppliers
   - Then: Expenses, Supplier Orders, Banking Transactions

### Phase C — PWA (1-2 hours)

4. Install `vite-plugin-pwa`
5. Configure manifest, Workbox, icons
6. Add meta tags to layouts
7. Build assets and test

### Phase D — Logo Integration (1 hour)

8. Replace sidebar logo
9. Update PDF quote template with logo
10. Update guest layout
11. Generate PWA icons from logo
12. Test all logo appearances

---

## Files Modified (Complete List)

### Layout
- `resources/views/layouts/app.blade.php` — sidebar Alpine scope, logo, PWA meta tags
- `resources/views/layouts/guest.blade.php` — logo, PWA meta tags

### Dashboard
- `resources/views/livewire/pages/dashboard.blade.php` — grid cols change

### List Views (9 files)
- `resources/views/livewire/customers/customer-list.blade.php`
- `resources/views/livewire/quotes/quote-list.blade.php`
- `resources/views/livewire/orders/order-list.blade.php`
- `resources/views/livewire/products/product-list.blade.php`
- `resources/views/livewire/suppliers/supplier-list.blade.php`
- `resources/views/livewire/sample-quotes/sample-quote-list.blade.php`
- `resources/views/livewire/expenses/expense-list.blade.php`
- `resources/views/livewire/expenses/supplier-order-list.blade.php`
- `resources/views/livewire/banking/transaction-list.blade.php`

### PWA/Config
- `vite.config.js` — add VitePWA plugin
- `package.json` — add vite-plugin-pwa dev dep

### Logo
- `resources/views/pdf/quote.blade.php` — replace inline icon with logo
- `resources/views/components/application-logo.blade.php` — optional update

### New Assets
- `public/images/pwa-192x192.png`
- `public/images/pwa-512x512.png`
- `public/images/apple-touch-icon.png`
- `public/favicon.svg` (optional)

---

## Verification

After implementation, test:

1. **Mobile sidebar:** Open on a mobile viewport (375px width), tap burger icon, verify sidebar slides in and overlay appears
2. **Dashboard cards:** Verify 2×2 grid on mobile, 4 columns on desktop
3. **List views:** Verify cards appear on mobile, tables on desktop for every list
4. **PWA:** Run `npm run build`, verify `manifest.webmanifest` and service worker are generated in `public/build/`, test "Add to Home Screen" in Chrome DevTools
5. **Logo:** Check sidebar, PDF output, guest login page all show the SVG logo
6. **Pint:** Run `vendor/bin/pint --format agent` after all PHP changes
7. **Build:** Run `npm run build` to confirm no Vite errors
