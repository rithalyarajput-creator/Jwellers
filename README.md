# ForeverKids POS

A point-of-sale and store-management web application for ForeverKids, built on **Laravel 12** (PHP 8.2), Livewire, Alpine.js, and Tailwind CSS v4.

## Tech stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Livewire 4, Alpine.js, Tailwind CSS v4 (via Vite 6)
- **Search:** Laravel Scout + Meilisearch
- **Auth / roles:** Laravel Sanctum, Spatie Laravel Permission
- **Database:** SQLite by default (MySQL supported)

## Getting started

Requirements: PHP 8.2+, Composer, Node.js 18+, and (optionally) Meilisearch.

```bash
# 1. Install PHP and JS dependencies
composer install
npm install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Create the database (SQLite) and run migrations
touch database/database.sqlite   # if using the default sqlite driver
php artisan migrate

# 4. Build front-end assets
npm run build
```

### Running locally

```bash
# Runs server, queue worker, log tailer, and Vite together
composer run dev
```

Or start pieces individually:

```bash
php artisan serve      # http://localhost:8000
npm run dev            # Vite dev server with HMR
```

## Configuration

Copy `.env.example` to `.env` and fill in the values you need. Integrations that read from the environment include:

- **Analytics / marketing:** GA4, Facebook Pixel
- **AI chatbot (Nia):** Meta Platforms / WhatsApp, Anthropic API
- **Checkout:** Shiprocket hosted checkout

> **Never commit `.env`, API keys, or `*.pem` / `*.key` files.** They are already excluded in `.gitignore`.

## Documentation

- `doc/` — project documentation
- `ForeverKids_POS_User_Guide.pdf` — end-user guide for the POS

## Testing

```bash
composer run test
```
