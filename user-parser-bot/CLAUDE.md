# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Telegram userbot that monitors channels/groups/chats using a regular user account (not a bot) and extracts structured data from messages. Part of the Dnipro Map project — extracts geographic coordinates from danger reports to feed the main map application.

## Tech Stack

- **Python 3.9+** (uses `zoneinfo`)
- **Telethon** — primary Telegram userbot client (recommended)
- **Pyrogram** — alternative Telegram userbot client
- **geo2.py** — hybrid address extraction: AI backends (Groq Llama 3.3, Google Gemini) with offline NLP fallback (natasha + pymorphy2 + regex)
- **geopy/Nominatim** — free geocoding (OpenStreetMap)
- **natasha + pymorphy2** — Russian/Ukrainian NLP for offline address extraction

## Commands

```bash
# Install dependencies
pip install -r requirements.txt

# Run (Telethon — recommended)
python parser_telethon.py

# Run (Pyrogram — alternative)
python parser_pyrogram.py

# First run requires interactive Telegram auth (phone + verification code)
# Session file is saved and reused automatically afterwards
```

No test suite, linter, or CI/CD is configured.

## Architecture

### Message Processing Pipeline

```
Telegram API (userbot account)
  → Parser (parser_telethon.py / parser_pyrogram.py)
    → Entity extraction (URLs, hashtags, mentions, emails, phones, formatting)
    → Address extraction (geo2.py) via ThreadPoolExecutor (non-blocking)
      → AI backends: Groq → Gemini → Ollama (tried in order)
      → Offline fallback: regex patterns → Natasha NER → pymorphy2 normalization
      → Nominatim geocoding
    → Media handling (media_downloader_*.py)
  → Output: log chat (HTML formatted) + optional JSONL file
```

### Key Modules

- **`parser_telethon.py` / `parser_pyrogram.py`** — Dual implementations with identical data model (`ParsedPost` dataclass). Async event-driven message handlers. Extract text, entities, media metadata, sender info, forwards, replies, reactions.
- **`geo2.py`** — `extract_and_geocode(text)` returns `GeoResult` with parsed address, lat/lng, confidence score, and extraction method. Heavy NLP resources (natasha segmenter, embeddings, taggers) loaded once at import.
- **`media_downloader_telethon.py` / `media_downloader_pyrogram.py`** — `download_and_attach()` returns `MediaResult`. Configurable via `ATTACH_MODE` (file/link/auto/both) and `MAX_DOWNLOAD_SIZE`.
- **`tz_helper.py`** — UTC-to-local timezone conversion utilities.

### Key Data Structures

- **`ParsedPost`** — dataclass with source metadata, sender info, text, entity buckets (urls, hashtags, mentions, emails, phones, bold, italic, code, spoiler), media metadata, forward/reply refs, reactions, view counts.
- **`GeoResult`** — dataclass with parsed address components (street type, name, building, city), confidence score (0.0–1.0), lat/lng, display name, extraction method.
- **`MediaResult`** — file path, size, MIME type, download method, public link.

## Environment Variables

**Required (Telegram auth):**
- `API_ID` / `API_HASH` — from https://my.telegram.org/apps (see `GET_TELEGRAM_API.txt`)
- `SESSION_NAME` — session file prefix
- `PHONE` — Telegram phone number

**Monitoring:**
- `WATCH_CHATS` — comma-separated channel/group usernames or IDs (blank = all)
- `LOG_CHAT` — destination for parsed summaries (blank = console only)
- `JSON_LOG_PATH` — optional JSONL output file path

**Geocoding (choose one):**
- `GROQ_API_KEY` — free at console.groq.com
- `GEMINI_API_KEY` — free at aistudio.google.com

**Media:**
- `DOWNLOAD_DIR`, `MAX_DOWNLOAD_SIZE` (default 50MB), `ATTACH_MODE` (file/link/auto/both), `KEEP_FILES`

**Timezone:**
- `TIMEZONE` — IANA name (default: UTC)
- `DATE_FORMAT` — strftime format
