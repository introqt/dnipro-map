# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Dnipro Map is a Telegram Mini App for reporting and viewing danger points on a map of Dnipro (Ukraine). Users interact via a Telegram bot that opens a web-based map interface. Admins can create/edit/delete points; regular users can view points and subscribe to location-based notifications.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+, SQLite (default)
- **Frontend:** Vite 7, Tailwind CSS 4, vanilla JS (Blade templates)
- **Testing:** Pest 3 (with RefreshDatabase trait applied globally in `tests/Pest.php`)
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

No traditional auth. Users are identified by Telegram ID:
- `AuthenticateTelegram` middleware resolves users via `X-Telegram-Id` header or `telegram_id` query param
- Users are auto-created when they first interact with the bot (`TelegramService::resolveUser`)
- Admin role is assigned by matching `ADMIN_TELEGRAM_ID` env var

### Domain Models

- **User** — identified by `telegram_id`, has a `UserRole` enum (User/Admin)
- **Point** — a danger point with lat/lng, description, photo_url, and `PointStatus` enum (Active/Archived). Has a computed `color` attribute based on age (red→yellow→green→gray over 3 hours)
- **Subscription** — links a user to a geographic area (lat/lng + radius_km) for notifications

### API Layer

- Controllers use the `ApiResponse` trait (`app/Http/Concerns/ApiResponse.php`) for consistent JSON envelope: `{ success, message, data/errors }`
- Form requests: `StorePointRequest`, `UpdatePointRequest`, `StoreSubscriptionRequest`
- API resources: `PointResource`, `SubscriptionResource`

### Routes

- `GET /app` — main map view (Blade)
- `GET /admin` — admin panel view (Blade)
- `POST /telegram/webhook` — Telegram bot webhook (web routes, no CSRF)
- `GET /api/points` — public, lists active points
- `POST|PUT|DELETE /api/points` — admin-only, behind `AuthenticateTelegram` middleware
- `POST /api/subscriptions` — authenticated, behind `AuthenticateTelegram` middleware

### Telegram Integration

- `TelegramService` handles bot commands (`/start`), location sharing (creates subscriptions), and webhook management
- Bot token and admin ID configured via `TELEGRAM_BOT_TOKEN` and `ADMIN_TELEGRAM_ID` env vars
- Web app URL configured via `WEB_APP_URL` env var

## Environment Variables

Key app-specific vars (see `.env.example`):
- `TELEGRAM_BOT_TOKEN` — Telegram Bot API token
- `TELEGRAM_BOT_USERNAME` — bot username
- `WEB_APP_URL` — URL for the Telegram Mini App (map interface)
- `ADMIN_TELEGRAM_ID` — Telegram user ID granted admin role
