# Quantock Van Tech Job Tracker — Design System

This document defines the visual language, component patterns, and conventions used across the QVT Job Tracker staff admin application. Any agent working on this codebase should consult this file before making visual decisions.

The design language is **engineered warmth**: the precision and reliability of professional electrical work, expressed through clean geometry, disciplined spacing, and a restrained palette. It should feel like a specialist you can trust — not a generic template.

---

## Colour Palette

All colours are defined in `tailwind.config.js`. Never use raw hex strings in components.

| Token | Hex | Role |
|-------|-----|------|
| `ink` | `#0F172A` | Headings, primary text, dark backgrounds |
| `slate` | `#64748B` | Body text, secondary labels, placeholders |
| `copper` | `#B45309` | **Primary accent** — CTAs, active nav, focus rings, link colours |
| `copper-light` | `#D97706` | Hover states, price highlights |
| `copper-dark` | `#92400E` | Button hover/pressed states |
| `teal` | `#0F766E` | **Secondary accent** — success badges, trust signals, alternate highlights |
| `teal-light` | `#14B8A6` | Hover states on teal elements |
| `teal-dark` | `#115E59` | Pressed teal state, deep success indicators |
| `offwhite` | `#FAFBFC` | Page background, header background |
| `background-light` | `#F1F5F9` | Alternating section/card backgrounds (`bg-slate-50`) |
| `panel` | `#E2E8F0` | Card borders, dividers (`border-slate-200`) |

### Usage Rules
- **CTAs and primary actions**: `bg-copper` with white text, `hover:bg-copper-dark`.
- **Secondary/outline buttons**: `bg-white border-copper text-copper`, hover flips to `bg-copper text-white`.
- **Active nav items**: `bg-copper/10 text-copper`.
- **Success indicators** (badges, toasts): `bg-teal/10 text-teal-dark border-teal/20`.
- **Input focus rings**: `focus:border-copper focus:ring-copper`.
- **Informational elements** (e.g. "Open Quotes" card): blue (`bg-blue-50 text-blue-600`).
- **Warning elements**: amber (`bg-amber-50 text-amber-600`).
- **Danger/destructive actions**: red (`bg-red-600`, `text-red-600`).

---

## Typography

| Role | Family | Weights | Usage |
|------|--------|---------|-------|
| Display | Space Grotesk | 500, 600, 700 | Headlines (`h1`, `h2`), eyebrows, buttons, labels, stats |
| Body | Inter | 400, 500 | Paragraphs, descriptions, form labels, table cells |

### Type Scale

| Element | Size | Weight | Line Height |
|---------|------|--------|-------------|
| Page H1 | `text-2xl` | 600 | `tracking-tight` |
| Page H2 / Section | `text-base` → `text-lg` | 600 | `leading-6` |
| Card H3 | `text-sm` → `text-base` | 600 | — |
| Body | `text-sm` | 400 | `leading-relaxed` |
| Labels | `text-sm` | 500 | — |
| Caption | `text-xs` | 500 | — |
| Button | `text-sm` | 600 | — |

### Font Classes
- Headings: `font-display font-semibold tracking-tight`
- Buttons: `font-display font-semibold`
- Body: `font-sans text-sm text-slate-700`

---

## Spacing & Radius

| Element | Value |
|---------|-------|
| Card | `rounded-xl`, `shadow-sm` (or `shadow-card`), `border border-slate-200` |
| Button | `rounded-lg` |
| Input / Select / Textarea | `rounded-lg` |
| Input focus ring | `ring-copper` |
| Table container | `rounded-xl`, `shadow-sm`, `border border-slate-200` |
| Form spacing | `space-y-5` |
| Card internal | `p-6` |
| Page container | `max-w-6xl` with `p-8` main area |
| Stat card | `p-5`, `rounded-xl`, `shadow-sm`, `border border-slate-200` |

---

## Components

### Button — Primary
```
<button class="inline-flex items-center justify-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-display font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
```

### Button — Secondary / Outline
```
<button class="inline-flex items-center justify-center gap-2 rounded-lg border-2 border-copper bg-white px-5 py-2.5 text-sm font-display font-semibold text-copper hover:bg-copper hover:text-white focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
```

### Button — Danger
```
<button class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-5 py-2.5 text-sm font-display font-semibold text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
```

### Input
```
<input class="w-full rounded-lg border-slate-300 text-ink placeholder-slate-400 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
```

### Select
```
<select class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
```

### Label
```
<label class="block text-sm font-medium text-slate-700 mb-1.5">
```

### Error
```
<ul class="text-sm text-red-600 space-y-1">
```

### Badge
```
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-teal/10 text-teal-dark border border-teal/20">
```

### Card
```
<div class="bg-white rounded-xl border border-slate-200 shadow-sm">
```

### Stat Card
```
<a class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-colors">
```

<!-- Icon inside stat card: -->
```
<div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center">
    <lucide-icon class="w-5 h-5 text-copper" />
</div>
```

### Table
```
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200">
        <!-- header: bg-slate-50 -->
        <!-- body row: even:bg-slate-50/50 -->
    </table>
</div>
```

### Toast — Success
```
<div class="flex items-center gap-3 rounded-lg shadow-lg border border-teal/20 px-4 py-3 text-sm font-medium bg-white text-teal-dark">
    <svg class="w-5 h-5 text-teal">...</svg>
    <span>Message</span>
</div>
```

### Toast — Error
```
<div class="flex items-center gap-3 rounded-lg shadow-lg border border-red-200 px-4 py-3 text-sm font-medium bg-white text-red-800">
    <svg class="w-5 h-5 text-red-500">...</svg>
    <span>Message</span>
</div>
```

### Sidebar Nav Item
```
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
    {{ active ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
    <lucide-icon class="w-5 h-5 shrink-0" />
    Label
</a>
```

---

## Layout Patterns

### Guest Pages (Login, Register, Password Reset)
```
<div class="min-h-screen flex flex-col justify-center items-center px-4 bg-slate-50">
    <div class="mb-8 text-center">
        <!-- logo tile bg-copper, brand text -->
    </div>
    <div class="w-full max-w-md">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-8">
                <!-- form content -->
            </div>
        </div>
    </div>
</div>
```

### App Shell (Authenticated)
- Sidebar: fixed left, `w-64`, `bg-white`, `border-r border-slate-200`.
- Main area: flex-1 with top bar (`h-16`, `bg-white`, `border-b border-slate-200`) and content (`p-4 lg:p-8`).

### Content Pages
```
<div class="max-w-6xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-ink tracking-tight">Page Title</h1>
        <p class="mt-1 text-sm text-slate-500">Page description</p>
    </div>
    <!-- content -->
</div>
```

---

## Iconography

- Use **Lucide** icons exclusively.
- Size: `w-5 h-5` for inline, `w-6 h-6` for navigation, `w-4 h-4` for table actions.
- Icon colour inherits from parent text colour or uses explicit utility (e.g. `text-copper`).

---

## Conventions Checklist

Before submitting changes to any page, verify:

- [ ] All colours use Tailwind theme tokens or the standard Tailwind palette (no raw hexes).
- [ ] No `dark:` utilities present (light mode only).
- [ ] Primary CTAs use copper.
- [ ] Success states (badges / toasts) use teal, not copper.
- [ ] All headings use `font-display` with appropriate weight.
- [ ] Inputs have `focus:border-copper focus:ring-copper`.
- [ ] No `emerald-*` classes remain.
- [ ] Trade prices are never exposed in customer-facing (email/PDF) views.
- [ ] `npm run build` succeeds without errors.

---

*Last updated: July 2026*
