# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Behavioral Guidelines

These rules govern how Claude approaches every task. They reduce common LLM coding mistakes by biasing toward caution and precision over speed.

### 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:

- State assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them — don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask: *Would a senior engineer say this is overcomplicated?* If yes, simplify.

### 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:

- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it — don't delete it.

When your changes create orphans:

- Remove imports, variables, or functions that **your changes** made unused.
- Don't remove pre-existing dead code unless explicitly asked.

**The test:** Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:

- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:

```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria allow independent looping. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working when:** diffs have fewer unnecessary changes, there are fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.

---

## About Laraship

Laraship is a modular Laravel-based platform by Corals for building marketplaces, e-commerce, directories, reservation, subscription, and classified sites. Modules are pluggable and dynamically registered from the database.

---

## Commands

### Frontend

- **Dev build (watch):** `npm run watch` or `npm run dev`
- **Production build:** `npm run production`
- Uses **Laravel Mix** (webpack), NOT Vite. Compiled assets land in `public/assets/`.

### Testing

- Run all tests: `php artisan test --compact`
- Run a single file: `php artisan test --compact tests/Feature/ExampleTest.php`
- Filter by name: `php artisan test --compact --filter=testName`
- Tests use a **real database** (not SQLite in-memory) — see `phpunit.xml`.

### Custom Artisan Commands

- `php artisan corals:install` — interactive installation wizard
- `php artisan make:module {ModuleName} {MainModel} [--modal]` — scaffold a new module from the Foo template
- `php artisan corals:modules` — module manager (runs in recovery mode, minimal boot)

---

## Architecture

### Two-Tier Module System

**Core modules** (`Corals/core/`) are always loaded via `CoralServiceProvider` → `FoundationServiceProvider`. They form the framework:

| Module | Purpose |
|---|---|
| `Foundation` | Base controllers, DataTables, hooks, helpers, API transformers, breadcrumbs |
| `User` | Auth, roles, permissions, 2FA, social login |
| `Settings` | Application settings, module management |
| `Theme` | Multi-theme engine, view overrides per theme |
| `Activity` | Spatie activity log integration |
| `Media` | File/image management |
| `Menu` | Dynamic navigation menus |
| `Utility` | LOV (list of values), tags, categories, locations, comments, ratings, wishlists |

**Dynamic modules** (`Corals/modules/`) are registered at runtime by `ModulesServiceProvider`, which reads the `modules` DB table (`enabled=1`) and calls each module's service provider. A disabled module loads nothing.

Each module is a self-contained mini-app with its own: `routes/`, `resources/views/`, `database/migrations/`, `Models/`, `Http/Controllers/`, `DataTables/`, `Policies/`, `Transformers/`, and a `module.json` manifest.

### Key Base Classes

- `Corals\Foundation\Http\Controllers\BaseController` — extend for all admin controllers; sets theme, auth middleware, and shared view data.
- `Corals\Foundation\Http\Controllers\APIBaseController` / `APIPublicController` — for API endpoints.
- `Corals\Foundation\DataTables\BaseDataTable` — extend for all list views (Yajra DataTables).
- `Corals\Foundation\Policies\BasePolicy` — base for all model policies.
- `Corals\Foundation\View\Transformers\Transformer` — extends League Fractal; used for API resource transformation.

### WordPress-style Hook System

Two facades provide a WordPress-like event/filter system used extensively across modules:

```php
// Actions: fire-and-forget side effects
Actions::add_action('hook_name', [$this, 'method'], $priority);
Actions::dispatch('hook_name', [$arg1, $arg2]);

// Filters: transform a value through a pipeline
Filters::add_filter('hook_name', [$this, 'method'], $priority);
$value = Filters::do_filter('hook_name', $value, ...$extraArgs);
```

Use these to extend module behavior without modifying core code.

### Theme System

Themes live in `resources/themes/{theme-name}/`. The `Theme` facade resolves view paths so a theme can override any view. Admin themes are database-configurable per user session. Each theme has a `theme.json` manifest.

### Frontend Stack

- **Vue 2** + **Vuex 3** (not Vue 3)
- **Bootstrap 4** + **jQuery 3**
- **laravel-echo** + **socket.io** for real-time events
- **axios** for HTTP
- Build entry points are declared in `webpack.mix.js`

### URL IDs

Public URLs use **Hashids** for hashed IDs. Use `hashids()->encode($id)` / `hashids()->decode($hash)` rather than exposing raw integer IDs.

---

## Laravel Boost Guidelines

### Foundation Rules

This application is a Laravel application. Main ecosystem packages and versions:

- php — 8.3
- laravel/framework (LARAVEL) — v13
- laravel/prompts (PROMPTS) — v0
- laravel/sanctum (SANCTUM) — v4
- laravel/socialite (SOCIALITE) — v5
- livewire/livewire (LIVEWIRE) — v3
- laravel/boost (BOOST) — v2
- laravel/mcp (MCP) — v0
- phpunit/phpunit (PHPUNIT) — v12
- laravel-echo (ECHO) — v1
- vue (VUE) — v2

### Skills Activation

This project has domain-specific skills available in `**/skills/**`. Activate the relevant skill whenever you work in that domain — don't wait until you're stuck.

### Conventions

- Follow all existing code conventions. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

### Verification Scripts

Don't create verification scripts or tinker when tests cover that functionality. Unit and feature tests are more important.

### Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change application dependencies without approval.

### Frontend Bundling

If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

### Documentation Files

Only create documentation files if explicitly requested by the user.

### Replies

Be concise in explanations — focus on what's important rather than explaining obvious details.

---

## Boost Tools

- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful; ignore old entries.

### Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

**Search Syntax:**

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

### Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

### Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval; prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

---

## PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

---

## Laravel 13

- **CRITICAL:** Always use `search-docs` for version-specific Laravel documentation and updated code examples.
- This project was upgraded from Laravel 10 through to Laravel 13 without adopting Laravel's streamlined skeleton (the `bootstrap/app.php`-centric structure introduced in Laravel 11). This is supported. Follow the existing Laravel 10 structure below unless the user explicitly requests migration. **Verify this still holds on 13** — if any part of the app has since moved to the streamlined skeleton, update this section to match.

### Laravel 10 Structure

- Middleware typically lives in `app/Http/Middleware/`; service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in a Laravel 10 structure:
  - Middleware registration: `app/Http/Kernel.php`
  - Exception handling: `app/Exceptions/Handler.php`
  - Console commands and schedule registration: `app/Console/Kernel.php`
  - Rate limits: likely in `RouteServiceProvider` or `app/Http/Kernel.php`

### Database

- When modifying a column, the migration must include all attributes that were previously defined on it. Otherwise they will be dropped.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

### New Artisan Commands

- `php artisan make:enum`
- `php artisan make:class`
- `php artisan make:interface`

---

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (migrations, controllers, models, etc.).
- If creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands; also pass the correct `--options` to ensure correct behavior.

### Model Creation

When creating new models, create useful factories and seeders too. Ask the user if they need anything else, using `php artisan make:model --help` to check available options.

### APIs & Eloquent Resources

For APIs, default to Eloquent API Resources and API versioning unless existing API routes do not follow this pattern — then follow existing application convention.

### URL Generation

When generating links to other pages, prefer named routes and the `route()` function.

### Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, use `php artisan make:test [options] {name}` for feature tests, or pass `--unit` for unit tests. Most tests should be feature tests.

---

## PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When tests relating to your feature are passing, ask the user if they'd like to run the entire test suite to verify nothing is broken.
- Tests should cover all happy paths, failure paths, and edge cases.
- Do not remove any tests or test files without approval. These are core to the application.

### Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- All tests: `php artisan test --compact`
- Single file: `php artisan test --compact tests/Feature/ExampleTest.php`
- Filter by name: `php artisan test --compact --filter=testName` (recommended after changing a related file)

---

## Deployment

Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.