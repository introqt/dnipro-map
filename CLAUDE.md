# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Dnipro Map is a Telegram Mini App for reporting and viewing danger points on a map of Dnipro (Ukraine). Users interact via a Telegram bot that opens a web-based map interface. Admins can create/edit/delete points; regular users can view points, vote, comment, and subscribe to location-based notifications.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.4+, SQLite (default)
- **Frontend:** Vite 7, Tailwind CSS 4, vanilla JS (Blade templates), Leaflet 1.9.4 (maps)
- **Testing:** Pest 3 (with `RefreshDatabase` trait applied globally in `tests/Pest.php`)
- **Linting:** Laravel Pint

## Commands

```bash
# Full project setup (install deps, generate key, migrate, build assets)
composer setup

# Development server (runs Laravel server, queue, Pail logs, and Vite concurrently)
composer dev

# Run all tests
composer test

# Run a single test file
php artisan test --filter=PointControllerTest

# Run a specific test by name
php artisan test --filter="it can list active points"

# Lint / fix code style
./vendor/bin/pint

# Set Telegram webhook
php artisan telegram:set-webhook {url?}
```

## Architecture

### Authentication

Two authentication mechanisms, no traditional username/password login for end users:

- **API (Telegram ID):** `AuthenticateTelegram` middleware resolves users via `X-Telegram-Id` header or `telegram_id` query param. Users are auto-created on first interaction. Admin role assigned when telegram_id matches `ADMIN_TELEGRAM_ID` env var (comma-separated list of IDs).
- **Admin panel (password):** `AuthenticateAdmin` middleware checks for `admin_telegram_id` in session. Login at `/admin/login` requires `ADMIN_PASSWORD` env var. Also accepts Telegram ID header/query param for API-style admin access.

### Domain Models

- **User** — identified by `telegram_id`, has a `UserRole` enum (User/Admin). Has many points, subscriptions, and votes.
- **Point** — danger point with lat/lng, description, photo_url, `type` (static_danger/moving_person/danger_road), and `PointStatus` enum (Active/Archived). Has a computed `color` attribute based on age (red ≤1h → yellow 1-2h → green 2-3h → gray >3h). Has many votes and comments.
- **Vote** — like/dislike on a point. Unique constraint on (point_id, user_id). `VoteType` enum (Like/Dislike).
- **Comment** — text comment on a point. User is nullable (anonymous comments allowed).
- **Subscription** — links a user to a geographic area (lat/lng + radius_km) for notifications.
- **ChannelMessage** — stores messages from external channels for admin review. Unique constraint on (channel_id, message_id). Has parsed coordinates, text, keywords (JSON), and metadata (JSON).

### API Layer

- Controllers use the `ApiResponse` trait (`app/Http/Concerns/ApiResponse.php`) for consistent JSON envelope: `{ success, message, data/errors }`
- Form requests: `StorePointRequest`, `UpdatePointRequest`, `StoreSubscriptionRequest`
- API resources: `PointResource`, `SubscriptionResource`
- Coordinate validation bounds (Dnipro area): lat 48.35–48.60, lng 34.90–35.15

### Routes

**Web routes** (`routes/web.php`):
- `GET /` — redirects to `/app`
- `GET /app` — main map view (Blade)
- `GET /admin/login` — admin login form
- `POST /admin/login` — admin login action
- `POST /admin/logout` — admin logout
- `GET /admin` — admin panel (behind `AuthenticateAdmin`)
- `GET /admin/channel-messages` — channel message review (behind `AuthenticateAdmin`)
- `POST /admin/channel-messages/{id}/process|destroy` — channel message actions (behind `AuthenticateAdmin`)
- `POST /telegram/webhook` — Telegram bot webhook (CSRF exempt)

**API routes** (`routes/api.php`):
- `GET /api/points` — public, lists active points from last 3 hours
- `GET /api/points/{point}/comments` — public, lists comments on a point
- `POST /api/channel-messages` — external channel webhook (requires secret)
- Authenticated (behind `AuthenticateTelegram`):
  - `POST|PUT|DELETE /api/points/{point}` — admin-only point CRUD
  - `GET|POST|DELETE /api/subscriptions` — user subscription management
  - `POST /api/upload` — image upload (jpg/jpeg/png/webp/gif, max 5MB)
  - `POST /api/points/{point}/vote` — like/dislike a point
  - `POST /api/points/{point}/comments` — add a comment

### Events & Listeners

- `PointCreated` event dispatched when a point is created via API
- `NotifyNearbySubscribers` queued listener finds subscriptions within range using Haversine formula and sends Telegram notifications

### Telegram Integration

- `TelegramService` handles bot commands (`/start`), location sharing (creates subscriptions with 5km default radius), and webhook management
- Bot token and admin ID configured via `TELEGRAM_BOT_TOKEN` and `ADMIN_TELEGRAM_ID` env vars
- Web app URL configured via `WEB_APP_URL` env var

### Frontend

- **Map view** (`resources/views/app.blade.php`): Leaflet map centered on Dnipro (48.4647, 35.0461). Shows color-coded circle markers for danger points. Includes subscription panel, type filter buttons, point popups with voting/comments.
- **Admin panel** (`resources/views/admin.blade.php`): Dual-view (list/map) for managing points. Point creation/editing form. Search and filter by type/status.
- **Base layout** (`resources/views/layouts/base.blade.php`): Loads Leaflet, Telegram WebApp SDK. Provides global `getTelegramId()` and `apiHeaders()` JS helpers.
- All frontend JS is inline in Blade templates (no bundled JS framework).

## Environment Variables

Key app-specific vars (see `.env.example`):
- `TELEGRAM_BOT_TOKEN` — Telegram Bot API token
- `TELEGRAM_BOT_USERNAME` — bot username
- `WEB_APP_URL` — URL for the Telegram Mini App (map interface)
- `ADMIN_TELEGRAM_ID` — comma-separated Telegram user IDs granted admin role
- `ADMIN_PASSWORD` — password for admin panel login at `/admin/login`
- `CHANNEL_WEBHOOK_SECRET` — secret for external channel message webhook (`app.channel_webhook_secret` config)

## Testing Conventions

- Pest 3 with `RefreshDatabase` applied globally — no need to add the trait in individual test files
- Feature tests cover: PointController, SubscriptionController, VoteController, UploadController, TelegramWebhook, AdminAuth, ChannelMessageWebhook
- Unit tests cover: Point model color computation
- Telegram API calls are mocked with `Http::fake()` in tests
- Factories available for: User (with `admin()` state), Point (with `archived()` state), Subscription, Vote
