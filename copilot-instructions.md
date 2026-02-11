# Copilot Instructions for Dnipro Map

## Project Overview

Dnipro Map is a Telegram Mini App for reporting and viewing danger points on a map of Dnipro (Ukraine). Users interact via a Telegram bot that opens a web-based map interface.

**Tech Stack:**
- Backend: Laravel 12, PHP 8.2+, SQLite (default)
- Frontend: Vite 7, Tailwind CSS 4, vanilla JS (Blade templates)
- Testing: Pest 3 (with RefreshDatabase trait applied globally)
- Linting: Laravel Pint

## Build, Test, and Lint Commands

```bash
# Full project setup (install deps, generate key, migrate, build assets)
composer setup

# Development server (Laravel, queue, logs, Vite all running concurrently)
composer dev

# Run all tests
composer test

# Run a single test file
php artisan test --filter=PointControllerTest

# Run a specific test by name
php artisan test --filter="it can list active points"

# Lint and fix code style
./vendor/bin/pint

# Set Telegram webhook
php artisan telegram:set-webhook {url?}
```

## Architecture

### Authentication Model

No traditional auth. Users are identified by Telegram ID:
- `AuthenticateTelegram` middleware resolves users via `X-Telegram-Id` header or `telegram_id` query param
- Users are auto-created on first interaction (`TelegramService::resolveUser`)
- Admin role is assigned by matching comma-separated `ADMIN_TELEGRAM_ID` env var
- Admin panel uses session-based authentication via password login

### Domain Models

- **User** — identified by `telegram_id`, has `UserRole` enum (User/Admin)
- **Point** — danger point with lat/lng, description, photo_url, and `PointStatus` enum (Active/Archived). Has a computed `color` attribute based on age (red→yellow→green→gray over 3 hours)
- **Subscription** — links user to geographic area (lat/lng + radius_km 1-10, default 2) for notifications
- **ChannelMessage** — parsed Telegram channel messages for admin review (channel_id, message_id, raw_message, parsed coordinates, keywords)

### API Response Pattern

All API controllers use the `ApiResponse` trait (`app/Http/Concerns/ApiResponse.php`) for consistent JSON envelope:
```php
{ success: bool, message?: string, data?: mixed, errors?: mixed }
```

Controllers return via `successResponse()` or `errorResponse()` methods.

### Routes Structure

**Web routes (`routes/web.php`):**
- `GET /app` — main map view (Blade)
- `GET /admin` — admin panel (protected by `AuthenticateAdmin` middleware)
- `GET /admin/channel-messages` — channel message review UI
- `POST /telegram/webhook` — Telegram bot webhook (no CSRF)

**API routes (`routes/api.php`):**
- `GET /api/points` — public, lists active points
- `POST /api/channel-messages` — webhook for parsed channel messages (protected by `CHANNEL_WEBHOOK_SECRET` header)
- Authenticated routes (behind `AuthenticateTelegram` middleware):
  - `POST|PUT|DELETE /api/points` — admin-only point management
  - `GET|POST|DELETE /api/subscriptions` — user subscription management
  - `POST /api/upload` — photo uploads
  - `POST /api/points/{point}/vote` — upvote/downvote points

### Telegram Integration

- `TelegramService` handles bot commands (`/start`), location sharing (creates subscriptions), and webhook management
- Bot token and admin ID(s) configured via `TELEGRAM_BOT_TOKEN` and `ADMIN_TELEGRAM_ID` (comma-separated) env vars
- Web app URL configured via `WEB_APP_URL` env var

### Channel Listener (External Service)

A separate Node.js userbot (`channel-listener.cjs`) connects to Telegram as a real user, listens for messages from `agendaDnepr` channel, parses coordinates/keywords, and POSTs to `/api/channel-messages`.

## Key Conventions

### Configuration Access

Always use `config('key')` not `env('KEY')` in application code (especially controllers) to ensure config caching works. Env vars should only be read in config files.

**Correct:**
```php
$secret = config('app.channel_webhook_secret');
```

**Wrong:**
```php
$secret = env('CHANNEL_WEBHOOK_SECRET'); // breaks after config:cache
```

### Subscription Radius Constraints

- Server: `StoreSubscriptionRequest` validates `radius_km` as `nullable|integer|min:1|max:10`
- Controller: defaults to 2 when omitted
- Frontend: slider enforces 1-10, default 2
- Location-based subscription via Telegram bot uses default 5 km (legacy)

### Admin Identification

- Multiple admins supported via comma-separated `ADMIN_TELEGRAM_ID` env var
- `AuthenticateAdmin` middleware parses env var and accepts any listed Telegram ID
- Admin role is set on user creation when telegram_id matches list

### Testing with Pest

- RefreshDatabase trait applied globally in `tests/Pest.php`
- Use `test('description', function() { ... })` format
- Authentication: use `withHeaders(['X-Telegram-Id' => $user->telegram_id])` to simulate Telegram user requests

### Frontend Map Integration

- Uses Leaflet.js for map rendering
- Point colors computed server-side based on age: red (<1h), yellow (1-2h), green (2-3h), gray (>3h)
- Voting and commenting use inline API calls with Telegram ID header
- Subscription modal shows as overlay with radius slider and geolocation permission request

## Environment Variables

Key app-specific vars (see `.env.example`):
- `TELEGRAM_BOT_TOKEN` — Telegram Bot API token
- `TELEGRAM_BOT_USERNAME` — bot username
- `WEB_APP_URL` — URL for Telegram Mini App (map interface)
- `ADMIN_TELEGRAM_ID` — comma-separated Telegram user IDs granted admin role (e.g., `123456789,987654321`)
- `ADMIN_PASSWORD` — password for admin panel login
- `CHANNEL_WEBHOOK_SECRET` — secret header value for channel message webhook (must match listener's `API_SECRET`)
