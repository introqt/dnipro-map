Configuration notes

- ADMIN_TELEGRAM_ID may contain comma-separated Telegram IDs, e.g.:

  ADMIN_TELEGRAM_ID=123456789,987654321

  The application will accept any Telegram ID listed in this env var as an admin for the admin UI/flows.

- Subscription radius constraints (server & frontend):
  - Minimum: 1 km
  - Maximum: 10 km
  - Default: 2 km

  The frontend slider and server-side validation enforce these values; the server will default radius_km to 2 when the client omits the value.