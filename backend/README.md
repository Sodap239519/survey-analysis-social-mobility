# Backend — Laravel API

## Requirements

- PHP 8.2+
- Composer
- SQLite (default) or MySQL/PostgreSQL

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# 3. Create SQLite database (default) OR configure DB_* in .env
touch database/database.sqlite

# 4. Run migrations
php artisan migrate

# 5. Seed questionnaire structure + admin user
php artisan db:seed

# 6. Start development server
php artisan serve
```

The API will be available at **http://localhost:8000**.

## Default Admin Credentials

| Field    | Value              |
|----------|--------------------|
| Email    | admin@example.com  |
| Password | password           |

> **Change this immediately in production!**

## Importing Production Data into Local MySQL

If you have downloaded `prod-database.sqlite` from the production server (Plesk), you can import
core data tables into your local MySQL database with the built-in artisan command:

```bash
php artisan app:import-prod-sqlite --truncate
```

See **[docs/import-prod-sqlite.md](docs/import-prod-sqlite.md)** for the complete step-by-step guide
(downloading from Plesk, setting `DB_PROD_SQLITE_PATH`, Windows path tips, and post-import verification).

## Running Tests

```bash
php artisan test
```

## Scoring Summary

- Multi-select: `score = min(max_score, sum(weights))`
- Exclusive "0)" choice → score = 0
- Q6 (Physical, 30 pts): no-problem → 30; sub-problems → `max(0, 30 - 5*count)`
- Capital normalized to 0–100; aggregate X in [1.0, 4.0]; poverty levels 1–4

> **TODO:** Confirm choice weights with domain experts/stakeholders.
