# AGENTS.md

Guidance for AI coding agents working in this repository.

## Project Overview

Dnipro Map is a Telegram Mini App for reporting/viewing danger points on a map of Dnipro, Ukraine. Users interact via a Telegram bot that opens a Leaflet.js map interface. Admins manage points; users view, vote, comment, and subscribe to location-based notifications.

**Stack:** Laravel 12 / PHP ^8.4 / SQLite / Pest 3 / Vite 7 / Tailwind CSS 4 / vanilla JS in Blade templates.

**Related Docs:** See `CLAUDE.md` for Claude IDE integration guidance and `copilot-instructions.md` for Copilot-specific instructions.

## Build / Dev / Test / Lint Commands

```bash
# Full setup (deps, key, migrate, build assets)
composer setup

# Dev server (Laravel + queue + Vite concurrently)
composer dev

# Run ALL tests
composer test

# Run a single test FILE
php artisan test --filter=PointControllerTest

# Run a single test by NAME (must match exactly)
php artisan test --filter="store creates a point for admin"

# Lint / auto-fix code style (Laravel Pint, default preset)
./vendor/bin/pint

# Check lint without fixing
./vendor/bin/pint --test

# Set Telegram webhook
php artisan telegram:set-webhook {url?}
```

Always run `./vendor/bin/pint` before committing. Always run `composer test` after making changes.

## Code Style & Formatting

### General

- **Indentation:** 4 spaces (2 for YAML). See `.editorconfig`.
- **Line endings:** LF. Final newline required.
- **Formatter:** Laravel Pint (default Laravel preset, no `pint.json`). Never add custom Pint config without asking.
- **No magic numbers/strings:** Extract into named constants or enums.
- **Early returns / guard clauses:** Avoid deep nesting. Return early on error conditions.
- **No nested ternaries.** Use `if/else` or `match` for complex logic.
- **No TODOs or placeholders** in generated code. All output must be fully implemented.

### PHP Conventions

- **PHP version:** ^8.4. Use modern features: `match`, named arguments, constructor promotion, `readonly`, enums.
- **Strict typing:** Always use typed parameters, return types, and property types.
- **Negation spacing:** `! $condition` (space after `!`), per Pint convention.
- **Imports:** One `use` per line, sorted alphabetically. Always use fully-qualified imports (never inline `\App\...` in code).
- **No `env()` in application code.** Use `config('key')` instead. `env()` breaks after `config:cache`. Env vars should only be read in `config/*.php` files.
- **Error handling:** Never use silent `catch` blocks. Always log, rethrow, or return meaningful error responses. Validate input before processing.

### Naming Conventions

- **Controllers:** PascalCase, singular noun + `Controller` (e.g., `PointController`). API controllers live under `App\Http\Controllers\Api\`.
- **Models:** PascalCase, singular (e.g., `Point`, `User`, `Subscription`).
- **Enums:** PascalCase, string-backed. Cases are PascalCase with lowercase string values (e.g., `case Active = 'active'`).
- **Middleware:** PascalCase, verb phrase (e.g., `AuthenticateTelegram`).
- **Form Requests:** `Store{Model}Request`, `Update{Model}Request`.
- **Resources:** `{Model}Resource`.
- **Events:** Past-tense verb phrase (e.g., `PointCreated`).
- **Listeners:** Descriptive verb phrase (e.g., `NotifyNearbySubscribers`).
- **Database:** snake_case table and column names. Migration files follow Laravel default naming.
- **Routes:** kebab-case URIs. API routes prefixed with `/api/`.
- **Test files:** `{Subject}Test.php` in `tests/Feature/` or `tests/Unit/`.

### Model Conventions

- Use `$fillable` arrays (not `$guarded`).
- Use the `casts()` method (not the `$casts` property) for attribute casting.
- Use `HasFactory` trait on all models.
- Type all relationship return types: `BelongsTo`, `HasMany`, etc.
- Computed attributes use `get{Name}Attribute()` accessor pattern.

### Controller Conventions

- Use the `ApiResponse` trait (`App\Http\Concerns\ApiResponse`) on all API controllers.
- Return `$this->successResponse($data, $message, $code)` or `$this->errorResponse($message, $code)`.
- JSON envelope: `{ success: bool, message: string, data?: mixed, errors?: mixed }`.
- Delegate validation to Form Request classes; do not validate inline in controllers.
- Authorization checks use `$request->user()->isAdmin()` with early-return error responses.

### Testing Conventions

- **Framework:** Pest 3. `RefreshDatabase` is applied globally via `tests/Pest.php`.
- **Test format:** `test('description', function () { ... })` -- not `it()`, not class-based.
- **Description style:** lowercase, descriptive phrase (e.g., `'store creates a point for admin'`).
- **Auth simulation:** `$this->withHeaders(['X-Telegram-Id' => $user->telegram_id])`.
- **Factories:** Use `User::factory()->admin()->create()` for admin users. Use `Point::factory(n)->create()` for batch creation.
- **Assertions:** Chain Laravel assertion methods: `->assertOk()`, `->assertCreated()`, `->assertJsonPath(...)`, `->assertDatabaseHas(...)`.
- **File location:** Feature tests in `tests/Feature/`, unit tests in `tests/Unit/`.

### Frontend Conventions

- Vanilla JS inline in Blade templates (no framework). Leaflet.js for map rendering.
- Tailwind CSS 4 for styling.
- API calls include `X-Telegram-Id` header for authenticated requests.

## Architecture Quick Reference

### Authentication

No traditional auth. Users identified by Telegram ID:
- `AuthenticateTelegram` middleware resolves user via `X-Telegram-Id` header or `telegram_id` query param.
- Users auto-created on first interaction.
- Admin role assigned when `telegram_id` matches comma-separated `ADMIN_TELEGRAM_ID` env var.
- Admin panel also supports session-based password auth (`ADMIN_PASSWORD` env var).

### Key Models

| Model | Key Fields | Notes |
|-------|-----------|-------|
| User | telegram_id, first_name, role (UserRole enum) | `isAdmin()` helper |
| Point | lat, lng, description, photo_url, status (PointStatus), type | `color` accessor (age-based) |
| Subscription | user_id, lat, lng, radius_km (1-10, default 2) | Geo notifications |
| Vote | user_id, point_id, type (VoteType enum) | Upvote/downvote |
| Comment | user_id, point_id, body | User comments on points |
| ChannelMessage | channel_id, message_id, raw_message, parsed coords | From external listener |

### Route Groups

- **Public:** `GET /api/points`
- **Telegram-authenticated:** subscriptions, votes, uploads (`AuthenticateTelegram` middleware)
- **Admin-only:** point CRUD (checked in controller via `isAdmin()`)
- **Webhook:** `POST /telegram/webhook` (no CSRF), `POST /api/channel-messages` (secret header)

### Events & Listeners

- `PointCreated` -> `NotifyNearbySubscribers` (queued, haversine distance calculation)

### Channel Listener (External)

A separate Node.js userbot (`channel-listener.cjs`) connects to Telegram as a real user, listens for messages from `agendaDnepr` channel, parses coordinates/keywords, and POSTs to `/api/channel-messages`.

### Point Color Calculation

Server-side computed based on age: red (<1h), yellow (1-2h), green (2-3h), gray (>3h).

## Environment Variables

Key app-specific vars (see `.env.example`):
- `TELEGRAM_BOT_TOKEN` -- Bot API token
- `TELEGRAM_BOT_USERNAME` -- bot username
- `WEB_APP_URL` -- Telegram Mini App URL
- `ADMIN_TELEGRAM_ID` -- comma-separated admin Telegram IDs
- `ADMIN_PASSWORD` -- admin panel login password
- `CHANNEL_WEBHOOK_SECRET` -- secret for channel message webhook

## Scope of Changes

- Only modify what is requested. Do not reformat unrelated code.
- Match the existing coding style of surrounding code.
- Ask for clarification if a request is ambiguous.
- Run `./vendor/bin/pint` and `composer test` after making changes.
