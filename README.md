<h1 align="center">LivePoll</h1>

<p align="center">
  Real-time surveys &amp; live audience polling — a self-hosted, open-source alternative to Mentimeter / Slido, built with Laravel.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Reverb-WebSockets-4f46e5" alt="Laravel Reverb">
  <img src="https://img.shields.io/badge/i18n-EN%20%7C%20PT-10b981" alt="English & Portuguese">
</p>

---

## Overview

LivePoll is two products in one:

- **Surveys** — a polished, multi-step survey wizard your audience fills in on their own device. Single, multiple, and limited-choice questions, per-question images/GIFs, a customizable theme color, optional respondent identification, and result dashboards with charts.
- **Live sessions** — Mentimeter/Slido-style live polling. Participants join a session from a QR code or link, and the presenter opens questions on the fly (A/B, A/B/C/D, or Yes/No). Votes stream back in real time over WebSockets, with live tally bars on both the presenter's control panel and the audience screens.

The whole interface is available in **English and Portuguese**, switchable on the fly.

## Features

### Surveys
- Step-by-step wizard with a progress bar and per-survey theme color.
- Question types: single choice, multiple choice, and limited choice (choose up to N).
- Attach an image per question, or search and pick a GIF via Giphy.
- Optional respondent name/email capture.
- Results dashboard with doughnut charts (Chart.js), date/name filters, and an identified-respondents table.
- Mark respondents as **VIP** to highlight their picks inside the wizard.

### Live polling
- Create a session, share a link or **QR code**, and let the audience join instantly.
- Open questions on the fly — no need to pre-write them; just pick the answer type.
- Real-time vote tallies broadcast via **Laravel Reverb** (WebSockets).
- **Master participants**: mark a participant so their choices appear as a suggestion to everyone else.
- Optional participant identification with name and photo.
- Draft / Live / Finished session lifecycle.

### Internationalization
- Full **English + Portuguese** UI, defaulting to English.
- Session-based language switch (`EN | PT`) available across the admin, survey, and live screens.

## Tech stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13, PHP 8.3+ |
| Realtime | Laravel Reverb (WebSocket server) |
| Auth | Laravel Breeze (Blade) |
| Frontend | Blade, Alpine.js, Tailwind CSS, Vite |
| Realtime client | Laravel Echo + pusher-js |
| Charts / QR | Chart.js, qrcode-generator |

## Requirements

- PHP 8.3+
- Composer
- Node.js 18+ and npm
- A database (MySQL, PostgreSQL, or SQLite)

## Getting started

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure your database in .env, then migrate
php artisan migrate

# 4. Link storage (for uploaded question/participant images)
php artisan storage:link

# 5. Build / watch the frontend
npm run dev
```

Then serve the app and start the realtime server (each in its own terminal):

```bash
php artisan serve         # HTTP
php artisan reverb:start  # WebSocket server for live polling
php artisan queue:work    # optional: process queued broadcasts
```

Visit the app, register an account, and head to **Surveys** or **Live Sessions** in the admin.

### Broadcasting (Reverb)

Live polling needs Reverb running and the matching env vars set. The defaults in `.env.example` work for local development:

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Giphy (optional)

To enable the "Search on Giphy" picker when building surveys, add a Giphy API key:

```dotenv
GIPHY_KEY=your-giphy-api-key
```

## Internationalization

Translations live in `lang/`:

- `lang/en/` and `lang/pt/` — PHP files grouped by domain (`messages`, `admin`, `survey`, `live`).
- `lang/pt.json` — Portuguese strings for the Breeze auth/profile screens.

English is the default/fallback locale. Users switch languages with the `EN | PT` switcher; the choice is stored in the session and applied by `App\Http\Middleware\SetLocale`.

To add another language, create a `lang/{locale}/` directory mirroring the English keys, add the locale to `SetLocale::SUPPORTED_LOCALES`, and add it to the `<x-locale-switcher>` component.

## Testing

```bash
php artisan test --compact
```

## License

Open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
