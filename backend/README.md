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
