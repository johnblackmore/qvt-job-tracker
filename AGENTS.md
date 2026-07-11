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

---

## MCP Server Maintenance Rules

When building or modifying staff admin functionality, agents must maintain parity between the web UI and the MCP server interface.

### Rules

1. **New Feature = New Tool**: Any new CRUD operation, status transition, or business action added to a Livewire component must have a corresponding MCP tool registered in `QvtServer`.
2. **Schema Parity**: When modifying validation rules, fillable fields, or business logic in a controller/Livewire form, update the matching MCP tool's `schema()` and `handle()` methods.
3. **Preview Pattern**: All new write tools must implement the `preview` / `confirmed` boolean parameter pattern (default `preview=true`, `confirmed=false`) to enable confirmation flows.
4. **Trade Price Rule**: Never expose `trade_price` or `total_trade` in any MCP tool response that could be used for customer-facing output. Internal-only read tools may expose trade data if clearly labeled.
5. **Route Registration**: New tool categories should be grouped in the `QvtServer::$tools` array with clear namespacing.
6. **Response Links**: Every tool returning a single record must include a `url` field using `route('model.show', $record)`. List responses must include `url` on each item.
7. **Chat-Forward Design**: Every tool must return a `message` field with natural language suitable for a chat UI. Error messages must be human-readable. Tool `#[Description]` attributes must be clear enough for an LLM to select the tool from a list.
8. **Testing**: Every new tool must have a PHPUnit feature test covering:
   - Preview mode returns correct preview data with no DB changes
   - Execute mode (`confirmed: true`) performs the action correctly
   - Validation errors return clear, actionable messages
   - Unauthenticated / non-admin requests return 401 / empty tool list

### MCP Directory Conventions

- `app/Mcp/Servers/` — Server definitions
- `app/Mcp/Tools/{Domain}/` — Grouped by business domain (Customers, Quotes, Orders, etc.)
- `app/Mcp/Resources/` — Read-only contextual data
- `app/Mcp/Prompts/` — Reusable AI prompt templates
- `routes/ai.php` — MCP route registration

### Updating AGENTS.md

If you add new business domains (e.g., Invoicing, Calendar), add the new tool category to the plan in `.opencode/plans/qvt-mcp-server-plan.md` and update this section.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
