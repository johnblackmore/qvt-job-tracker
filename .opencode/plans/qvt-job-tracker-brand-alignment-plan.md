# Plan — Align QVT Job Tracker to Quantock Van Tech Brand

## Overview

The QVT Job Tracker is currently styled with a **green-dominant emerald palette** that does not match the public-facing marketing site at `quantockvantech.com`. The marketing site uses a **copper primary** with a **teal secondary** ("engineered warmth") and a **Space Grotesk + Inter** type pairing. This plan brings the staff admin application into full visual alignment with the marketing site, documents the design system in a project-local `DESIGN.md`, and wires that doc into `AGENTS.md` so all future work is visually aligned by default.

Source-of-truth design references (read-only):
- `/Users/johnblackmore/Sites/quantock-van-tech/DESIGN.md`
- `/Users/johnblackmore/Sites/quantock-van-tech/tailwind.config.js`

---

## Goals

1. Rebrand the admin app from emerald → **copper primary / teal secondary**.
2. Adopt **Space Grotesk (display) + Inter (body)** typography.
3. Capture the design system in a project-local `DESIGN.md` (admin-app-scoped).
4. Reference that `DESIGN.md` from `AGENTS.md` and shrink the existing "Design System" section in `AGENTS.md` to a pointer.
5. Preserve all business rules (especially trade-price confidentiality) and avoid introducing dark mode.

## Non-Goals

- No dark mode.
- No `CircuitTrace` scroll animation (public-marketing-only).
- No daisyUI theme introduction (QVT brand is bespoke; daisyUI stays installed but unused).
- No changes to Phase 2 customer portal styling.

---

## Design Tokens (target)

Adopt the marketing-site tokens verbatim. Expose them in `tailwind.config.js`.

| Token | Hex | Use in Job Tracker |
|---|---|---|
| `ink` | `#0F172A` | Headings, primary text |
| `slate` | `#64748B` | Body text, secondary labels, placeholders (existing `text-slate-*` stays) |
| `copper` | `#B45309` | **Primary CTAs**, active nav, focus rings |
| `copper-light` | `#D97706` | Hover on copper, price highlights |
| `copper-dark` | `#92400E` | Pressed/active states |
| `teal` | `#0F766E` | Secondary accent, success badges, alternate highlights |
| `teal-light` | `#14B8A6` | Hover on teal |
| `teal-dark` | `#115E59` | Pressed teal |
| `offwhite` | `#FAFBFC` | Page background |
| `background-light` | `#F1F5F9` | Alternating section backgrounds (already `bg-slate-50`) |
| `panel` | `#E2E8F0` | Card borders / dividers (already `border-slate-200`) |
| `gradient-hero` | `linear-gradient(135deg, #FAFBFC 0%, #F1F5F9 100%)` | Login card + welcome background |
| `gradient-copper` | `linear-gradient(to right, #B45309, #D97706)` | Optional CTA gradient |
| `shadow-card` | as marketing site | Cards |
| `shadow-soft` | as marketing site | Subtle lift |

### Semantic mapping (for the global `emerald → copper/teal` swap)

| Current (emerald) | New (brand) | Used for |
|---|---|---|
| `bg-emerald-600` | `bg-copper` | Primary CTA fill |
| `hover:bg-emerald-700` | `hover:bg-copper-dark` | Primary CTA hover |
| `bg-emerald-50` | `bg-copper/10` | Subtle background (icon tile, badge) |
| `bg-emerald-100` | `bg-copper/15` | Avatar fill |
| `text-emerald-600` | `text-copper` | Links, icons |
| `text-emerald-700` | `text-copper` | Active nav text |
| `text-emerald-500` | `text-copper-light` | Icon hover |
| `hover:text-emerald-600/700` | `hover:text-copper` / `hover:text-copper-dark` | Link hover |
| `border-emerald-200` | `border-copper/20` | Badge border |
| `border-emerald-300` | `border-copper/30` | Card hover border |
| `hover:border-emerald-300` | `hover:border-copper/30` | Card hover |
| `focus:border-emerald-500` | `focus:border-copper` | Input focus border |
| `focus:ring-emerald-500` | `focus:ring-copper` | Input focus ring |
| Success badges (accepted, active, responded, sent) | `bg-teal/10 text-teal border-teal/20` | Status badges |
| Toast success border/text | `border-teal/20 text-teal-dark` | Toast |

Tailwind opacity syntax (`bg-copper/10`) requires RGB-channel colour objects in the config — handled in step 3.

---

## Implementation Steps

### Step 1 — Tailwind config
File: `tailwind.config.js`

- Add `colors.ink`, `colors.slate` (hex `#64748B`), `colors.copper{DEFAULT,light,dark}`, `colors.teal{DEFAULT,light,dark}`, `colors.offwhite`, `colors.panel`.
- Add `fontFamily.sans` (Inter) — already present via `defaultTheme`; switch first entry to `'Inter'`.
- Add `fontFamily.display` (`"Space Grotesk", system-ui, sans-serif`).
- Add `backgroundImage['gradient-hero']` and `backgroundImage['gradient-copper']`.
- Add `boxShadow.card`, `boxShadow.card-hover`, `boxShadow.soft` from the marketing site.
- Keep `@tailwindcss/forms` plugin.

### Step 2 — Fonts
File: `resources/css/app.css`

- Add Google Fonts `@import` for Inter (400/500/600) and Space Grotesk (500/600/700) at the top of the file.
- Leave the rest of the file as-is.

### Step 3 — Project-local design doc
New file: `DESIGN.md` (project root)

Modeled on the marketing site `DESIGN.md` but scoped to the admin app. Sections:

1. **Philosophy** — "Engineered warmth" applied to staff tooling.
2. **Colour palette** — full token table from "Design Tokens" above with role descriptions; emphasise copper primary, teal secondary.
3. **Typography** — Space Grotesk display (500/600/700) for headings, eyebrows, buttons, labels, stats; Inter (400/500) for body. Compressed type scale suitable for a dashboard.
4. **Spacing & radius** — `rounded-xl` for cards, `rounded-lg` for buttons/inputs; existing `space-y-5/6` form patterns are correct.
5. **Components** — Button (primary/secondary/danger), Input, Label, Error, Badge, Card, Table, Modal, Toast, Stat card, Sidebar nav, Top bar.
6. **Iconography** — Lucide only; `w-5 h-5` inline, `w-6 h-6` navigation. (Same as current `AGENTS.md`.)
7. **Conventions checklist** — mirrors marketing site + admin-specific items (e.g. "Never expose trade prices in styling — retail visuals only", "Light mode only", "No daisyUI theme").

### Step 4 — `AGENTS.md` updates
File: `AGENTS.md`

- Replace the entire current "Design System" section (Mode, Colour Palette, Typography, Spacing & Radius, Layout, Iconography) with a short pointer to `DESIGN.md`.
- Add a one-paragraph summary at the top of the new section: "Refer to `DESIGN.md` for all design tokens, component patterns, and conventions. Highlights: copper primary, teal secondary, Space Grotesk display, Inter body, light-mode only."
- Preserve everything else in `AGENTS.md` (business rules, MCP rules, conventions, env, etc.).

### Step 5 — Shared Blade components
Update each component file in `resources/views/components/`:

| File | Change |
|---|---|
| `primary-button.blade.php` | `bg-copper hover:bg-copper-dark focus:ring-copper`, `rounded-lg text-sm font-display font-semibold`, drop `uppercase tracking-widest`, drop dark utilities |
| `secondary-button.blade.php` | `bg-white border-copper text-copper`, hover flips to `bg-copper text-white`, `rounded-lg text-sm font-display font-semibold`, drop dark utilities + uppercase |
| `danger-button.blade.php` | Keep red palette; drop dark utilities, uppercase; `rounded-lg text-sm font-display font-semibold` |
| `text-input.blade.php` | `focus:border-copper focus:ring-copper` |
| `input-label.blade.php` | Already aligned (slate-700) |
| `input-error.blade.php` | Already aligned |
| `auth-session-status.blade.php` | `bg-copper/10 text-copper-dark border-copper/20` |
| `action-message.blade.php` | `text-copper` |
| `nav-link.blade.php` | Active state: `bg-copper/10 text-copper border-copper/20` |
| `responsive-nav-link.blade.php` | Same as `nav-link` |
| `dropdown.blade.php`, `dropdown-link.blade.php` | Hover/focus → copper |
| `modal.blade.php` | Confirm buttons use copper (CTA), cancel uses secondary |
| `application-logo.blade.php` | Tile background `bg-copper` |

### Step 6 — Layouts
- `resources/views/layouts/app.blade.php`
  - Sidebar logo tile: `bg-copper`.
  - Active nav: `bg-copper/10 text-copper`.
  - User avatar: `bg-copper/15 text-copper-dark`.
  - Role badge in top bar: `bg-copper/10 text-copper border-copper/20`.
  - Toast success: `border-teal/20 text-teal-dark`, icon `text-teal`.
  - Toast error stays red.
- `resources/views/layouts/guest.blade.php`
  - Card background uses `bg-gradient-hero` (or stays white with the gradient on the outer container).
  - Logo tile: `bg-copper`.
- `resources/views/welcome.blade.php`
  - Logo tile `bg-copper`, page bg `bg-gradient-hero`; CTA `bg-copper hover:bg-copper-dark focus:ring-copper`.

### Step 7 — Livewire pages
Apply the global `emerald → copper/teal` swap across all of these. Keep informational blue/amber/purple accents intact.

- `livewire/pages/dashboard.blade.php`
  - Add an eyebrow above the H1 (e.g. "Operations").
  - Stat card icons: `bg-copper/10 text-copper` (Customers); other cards keep their informational colours (blue Open Quotes, amber Pending Orders, teal Open Enquiries).
  - Quick-action tile icons: copper.
- `livewire/pages/auth/*.blade.php` (login, register, forgot, reset, confirm, verify-email)
  - H1 uses `font-display`.
  - CTA → copper.
  - Focus rings → copper.
  - Links → copper.
- `livewire/customers/*.blade.php`
- `livewire/products/*.blade.php` (list, form, show, category-list, category-form)
- `livewire/suppliers/*.blade.php`
- `livewire/orders/*.blade.php` (list, form, show)
- `livewire/quotes/*.blade.php` (list, builder, show)
- `livewire/enquiries/*.blade.php` (list, form)
- `livewire/email-templates/*.blade.php` (list, form)
- `livewire/sample-quotes/*.blade.php` (list, form)
- `livewire/vehicles/*.blade.php`
- `livewire/api-tokens/api-token-manager.blade.php`
- `livewire/profile/*.blade.php` (update-profile, update-password, delete-user)
- `livewire/actions/logout.blade.php`

### Step 8 — Email + PDF views (customer-facing)
- `resources/views/emails/quote-default.blade.php`
- `resources/views/pdf/quote.blade.php`
- Rebrand to the new palette so customer output matches the brand.
- **Do not** expose trade prices in either view (per the existing critical business rule).

### Step 9 — Global search/replace
Run scripted replacements in `resources/views/**/*.blade.php` to handle the ~300 occurrences. Mapping is the table under "Semantic mapping" above. Status indicators (`accepted`, `active`, `responded`, `sent`) map to **teal**, not copper, so success semantics are distinct from the primary accent.

### Step 10 — Build + verify
- `npm run build` — must succeed without class-not-found warnings.
- `php artisan test --compact` — all previously passing tests still pass. The two pre-existing failures (`layout.navigation` and `App\Livewire\Actions\Logout`) are unrelated and predate this work.
- `grep -r "emerald-" resources/` — should return zero results.
- Visual spot-check on: welcome, login, forgot/reset/confirm password, dashboard, customers list, quote builder, quote show, email template form, profile.

### Step 11 — Commit
- Single commit covering: tailwind config, fonts, `DESIGN.md`, `AGENTS.md`, all blade updates.
- No secrets, no config file changes beyond `tailwind.config.js`.

---

## Acceptance Criteria

- [ ] No `emerald-*` classes remain anywhere under `resources/`.
- [ ] `tailwind.config.js` exposes copper / teal / ink / slate / offwhite / panel tokens, plus display font + gradient + shadow tokens.
- [ ] `DESIGN.md` exists at project root and is the canonical design reference.
- [ ] `AGENTS.md` "Design System" section points to `DESIGN.md` and is short.
- [ ] Shared components (`primary-button`, `secondary-button`, `danger-button`, `text-input`, labels, nav-links, status badges) use the new palette.
- [ ] App layout (sidebar, top bar, toasts), guest layout, welcome page use the new palette.
- [ ] All livewire pages use the new palette; success badges use teal.
- [ ] Email + PDF templates use the new palette; trade prices remain hidden.
- [ ] `npm run build` succeeds.
- [ ] `php artisan test --compact` shows no new failures beyond the two pre-existing ones.
- [ ] No `dark:` utilities introduced.

---

## Risks / Open Questions

- **Tailwind opacity with hex colours**: `bg-copper/10` etc. require RGB-channel colour objects in the config. The plan above uses `DEFAULT`/`light`/`dark` keys; if the marketing site's exact token set is required to use `rgb`/`alpha` notation, the config will be adjusted. Will verify by inspecting the marketing site's build output before locking the config.
- **daisyUI leftover**: `daisyui` is in `package.json` but currently unused. Leaving it installed (not removing) to avoid an uninstall + asset regeneration in this pass; can be revisited later.
- **Customer-facing output (emails/PDF)**: visual rebrand is in scope, but copy and trade-price hiding must stay identical.

---

## Out of Scope

- Customer portal (Phase 2) styling.
- Animations / `CircuitTrace`.
- Dark mode.
- Removing daisyUI.
- Refactoring layouts away from Alpine sidebars.

---

*Last updated: July 2026*
