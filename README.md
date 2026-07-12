# ForeverKids

An e-commerce storefront with a full admin panel and built-in POS (point of sale),
built on **Laravel 12**, Livewire, Alpine.js, and Tailwind CSS v4.

Use it as-is, or as a **base template** for new store projects — clone it, rebrand
it from the admin panel, and go live.

---

## What you get

| Area          | Features                                                              |
| ------------- | --------------------------------------------------------------------- |
| **Storefront**| Product catalog, search, cart, checkout, customer accounts, blog       |
| **Admin**     | Products, categories, orders, customers, coupons, reviews, reports     |
| **POS**       | In-store register, cash movements, shifts, barcode/thermal labels      |
| **Settings**  | Site name, logo, currency, SEO, email, payments, shipping, tax         |
| **Users**     | Roles & permissions (Spatie), auth, email verification                 |

---

## Requirements

| Tool                     | Version                                                  |
| ------------------------ | -------------------------------------------------------- |
| **PHP**                  | **8.4+**                                                 |
| **Composer**             | 2.x                                                      |
| **Node.js**              | 18+ (with npm)                                           |
| **MySQL** or **MariaDB** | MySQL 8+ / MariaDB 10.4+ — **required, not SQLite**       |

**Required PHP extensions:** `mbstring`, `openssl`, `pdo_mysql`, `curl`, `zip`,
`gd`, `intl`, `bcmath`, `fileinfo`, `sodium`

> ⚠️ **This app does not run on SQLite.** It uses MySQL fulltext indexes.
> On Windows, [XAMPP](https://www.apachefriends.org/) is the easiest way to get MySQL.

---

## Setup

### 1. Get the code

```bash
git clone <your-repo-url> my-store
cd my-store
composer install
npm install
```

### 2. Create the database

Open **phpMyAdmin** (<http://localhost/phpmyadmin>) → **New** → name it `foreverkids`.

Or from the command line:

```sql
CREATE DATABASE foreverkids CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> The database starts **empty** — the next step creates all the tables for you.

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set your database details:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=foreverkids
DB_USERNAME=root
DB_PASSWORD=

# Leave empty so the storefront is public.
# Set a value to turn on the "Coming Soon" gate.
PRELAUNCH_PASSWORD=
```

### 4. Create tables + demo data

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

This creates all tables and seeds demo data (~46 products, ~43 categories, and the
login accounts below).

> Want an **empty store** instead — no demo products? Run `php artisan migrate`
> (without `--seed`), then create your admin manually. See
> [Starting a new project](#starting-a-new-project-from-this-template).

### 5. Run it

```bash
php artisan serve
```

| | URL |
| --- | --- |
| 🛍️ **Storefront** | <http://127.0.0.1:8000> |
| 🔐 **Admin panel** | <http://127.0.0.1:8000/admin> |

**Make sure MySQL is running first** (in XAMPP Control Panel, click **Start** next to MySQL).

---

## Login details

These accounts are created by `php artisan migrate --seed`:

| Role              | Email                 | Password   |
| ----------------- | --------------------- | ---------- |
| **Super Admin**   | `admin@example.com`   | `password` |
| **Store Manager** | `manager@example.com` | `password` |

> 🚨 **These are demo passwords for local development only.**
> **Change them before putting the site online.** To change: log in to
> `/admin` → **Users** → edit the user → set a new password.

---

## Database

| | |
| --- | --- |
| **Engine** | MySQL / MariaDB |
| **Default name** | `foreverkids` (change it in `.env`) |
| **Host / port** | `127.0.0.1:3306` |
| **Configured in** | `.env` |
| **Table definitions** | `database/migrations/` |
| **Demo data** | `database/seeders/` |

You don't copy a `.sql` dump around — the schema lives in the migration files.
Running `php artisan migrate` builds every table from scratch.

Useful commands:

```bash
php artisan migrate           # create tables (empty)
php artisan migrate --seed    # create tables + demo data
php artisan migrate:fresh --seed   # ⚠️ WIPES the database, then rebuilds it
```

---

## Starting a new project from this template

To spin up a **fresh store for a new client**:

```bash
# 1. Clone into a new folder
git clone <your-repo-url> client-two
cd client-two
composer install && npm install

# 2. Create a NEW, empty database (e.g. "client_two") in phpMyAdmin

# 3. Point .env at it
cp .env.example .env
php artisan key:generate
#   -> edit .env:  DB_DATABASE=client_two

# 4. Build the tables
php artisan migrate          # empty store (no demo products)
php artisan storage:link
npm run build

# 5. Create your admin login
php artisan tinker
```

Then inside tinker:

```php
$u = App\Models\User::create([
    'first_name' => 'Admin',
    'last_name'  => 'User',
    'email'      => 'you@yourdomain.com',
    'password'   => Hash::make('YOUR-STRONG-PASSWORD'),
    'role'       => 'admin',
    'is_verified'=> true,
    'is_active'  => true,
    'email_verified_at' => now(),
]);
App\Models\Admin::create(['user_id' => $u->id, 'role' => 'super_admin', 'is_active' => true]);
```

Finally, run `php artisan serve`, log in at `/admin`, and rebrand the store under
**Settings → General** (site name, logo, contact details, currency).

> ℹ️ **Heads-up:** the store name and logo appear in some Blade templates as
> hardcoded fallbacks, so a few pages may still say "ForeverKids" after you change
> the setting. Search the `resources/views/` folder for `ForeverKids` to catch them.

---

## Configuration

All optional integrations are configured in `.env`:

| Integration | Keys |
| ----------- | ---- |
| Analytics / ads | `GA4_MEASUREMENT_ID`, `FB_PIXEL_ID` |
| AI chatbot (Nia) | `META_PAGE_ACCESS_TOKEN`, `ANTHROPIC_API_KEY` |
| Checkout | `SHIPROCKET_CHECKOUT_*` |
| Coming-soon gate | `PRELAUNCH_PASSWORD` |

> 🔒 **Never commit `.env`, API keys, or `*.pem` / `*.key` files.**
> They're already listed in `.gitignore`.

---

## Troubleshooting

| Problem | Fix |
| ------- | --- |
| `SQLSTATE[HY000] [2002]` — can't connect to DB | MySQL isn't running. Start it in **XAMPP Control Panel**. |
| MySQL won't start in XAMPP | Something else is on port 3306. Stop it, then hit **Start** in XAMPP. |
| Page shows a `Deprecated:` warning at the top | You're on an older PHP. Use **PHP 8.4+**. |
| `Vite manifest not found` | Run `npm run build`. |
| Images / uploads show as broken | Run `php artisan storage:link`. |
| Blank page or stale config after editing `.env` | Run `php artisan config:clear && php artisan cache:clear`. |

---

## Development

```bash
composer run dev   # server + queue + logs + Vite, all at once
npm run dev        # Vite only, with hot reload
composer run test  # run the test suite
```

## More docs

- [`SETUP.md`](SETUP.md) — condensed setup steps
- `doc/` — project documentation
- `ForeverKids_POS_User_Guide.pdf` — POS user guide
