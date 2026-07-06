# Local setup guide

Step-by-step instructions to run ForeverKids POS on a local machine. Verified on
Windows with PHP 8.4, but the same steps work on macOS/Linux.

## Requirements

- **PHP 8.4+** with these extensions enabled: `mbstring`, `openssl`, `pdo_mysql`,
  `curl`, `zip`, `gd`, `intl`, `bcmath`, `fileinfo`, `sodium`
- **Composer 2.x**
- **Node.js 18+** and npm
- **MySQL 8 or MariaDB 10.4+** — this app is **not** SQLite-compatible
  (it uses fulltext indexes and a few raw MySQL migrations).

> On Windows, [XAMPP](https://www.apachefriends.org/) is the quickest way to get
> MariaDB running.

## 1. Create the database

```sql
CREATE DATABASE foreverkids CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 2. Install dependencies

```bash
composer install
npm install
```

## 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Then open `.env` and set your database credentials:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=foreverkids
DB_USERNAME=root
DB_PASSWORD=
```

Leave `PRELAUNCH_PASSWORD=` empty so the "Coming Soon" gate stays off and the
storefront is public.

## 4. Migrate, seed, and build

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

The seeders create demo data: ~46 products, ~43 categories, and login accounts.

## 5. Run

```bash
php artisan serve
```

Open <http://127.0.0.1:8000>.

## Demo accounts

Created by the database seeders (for local development only):

| Role          | Email                 | Password   |
| ------------- | --------------------- | ---------- |
| Super Admin   | `admin@example.com`   | `password` |
| Store Manager | `manager@example.com` | `password` |

- Storefront: <http://127.0.0.1:8000>
- Admin panel: <http://127.0.0.1:8000/admin>

> ⚠️ Change these passwords before deploying anywhere public.
