# Importing Production SQLite Data into Local MySQL

This guide explains how to copy the production database (downloaded as a SQLite file from Plesk)
into your local MySQL database so you can develop and test against real data.

---

## Prerequisites

- Local MySQL is running (e.g. via XAMPP) and `php artisan migrate` has already succeeded.
- You have access to the Plesk File Manager for your production site.

---

## Step 1 — Download `database.sqlite` from Plesk

1. Log in to the Plesk control panel.
2. Open **Files** (File Manager) for your domain.
3. Navigate to the Laravel `backend/` directory (e.g. `httpdocs/backend/` or similar).
4. Go into `database/` and download the file named **`database.sqlite`**.
5. Rename the downloaded file to **`prod-database.sqlite`** to avoid confusion with the local DB file.

---

## Step 2 — Place the File Locally

Put the file inside the `backend/database/` directory of your local clone:

```
survey-analysis-social-mobility/
└── backend/
    └── database/
        ├── database.sqlite       ← your local dev DB (if still using SQLite)
        └── prod-database.sqlite  ← the file you just downloaded
```

> **Windows path example:**
> `C:\xampp\GitHub\survey-analysis-social-mobility\backend\database\prod-database.sqlite`

---

## Step 3 — Set `DB_PROD_SQLITE_PATH` in `.env`

Open `backend/.env` and add (or uncomment) the following line, pointing to the file you placed in Step 2.

### Windows (XAMPP)

```env
DB_PROD_SQLITE_PATH=C:\xampp\GitHub\survey-analysis-social-mobility\backend\database\prod-database.sqlite
```

> **Tip:** Laravel/PHP on Windows accepts both `\` and `/` as path separators here.

### Linux / macOS

```env
DB_PROD_SQLITE_PATH=/home/yourname/projects/survey-analysis-social-mobility/backend/database/prod-database.sqlite
```

If `DB_PROD_SQLITE_PATH` is not set, the command falls back to
`backend/database/prod-database.sqlite` (relative to the Laravel `database_path()`).

---

## Step 4 — Run the Import Command

Navigate to the `backend/` directory, then run:

```bash
php artisan app:import-prod-sqlite --truncate
```

- `--truncate` clears each destination table before inserting, preventing duplicate-key errors.
  **Use this flag when importing for the first time or re-importing from scratch.**
- Omit `--truncate` if you only want to *add* missing rows (note: may fail on duplicate primary keys).
- `--chunk=500` (default) controls the number of rows inserted per batch; increase for speed, decrease if you hit memory limits.

The command will:
1. Verify the SQLite file exists.
2. Show you what it is about to do and ask for confirmation.
3. Disable MySQL foreign-key checks, import each core table in FK-safe order, then re-enable checks.
4. Print a post-import row-count summary.

**Tables imported (core data only):**

| Table | Description |
|---|---|
| `users` | Admin/staff accounts |
| `capitals` | Capital category definitions |
| `households` | Household master records |
| `persons` | Person records linked to households |
| `questions` | Survey question definitions |
| `choices` | Answer choices for each question |
| `survey_responses` | Per-person survey response header |
| `answers` | Individual question answers |
| `detailed_answers` | Detailed/sub-answers |

**Skipped (ephemeral/system tables):**
`cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches`, `sessions`,
`password_reset_tokens`, `personal_access_tokens`, `migrations`

---

## Step 5 — Post-Import Verification

After the command finishes it prints a row-count summary automatically.
You can also verify manually:

```bash
php artisan tinker
```

```php
// Quick row counts
collect(['users','households','persons','survey_responses','answers','detailed_answers'])
    ->each(fn($t) => dump($t . ': ' . DB::table($t)->count()));
exit
```

Or check via phpMyAdmin: open `http://localhost/phpmyadmin`, select your database, and inspect each table.

---

## Troubleshooting

| Problem | Solution |
|---|---|
| `SQLite file not found` | Check `DB_PROD_SQLITE_PATH` in `.env` and that the file exists at that exact path. |
| `SQLSTATE[23000]: Integrity constraint violation` | Run with `--truncate` to clear tables first. |
| `php_pdo_sqlite` extension not loaded | Enable `extension=pdo_sqlite` in your `php.ini` (XAMPP: edit `php.ini` in `C:\xampp\php\`). |
| Out of memory / slow | Lower `--chunk` value, e.g. `--chunk=100`. |
| Thai characters garbled | Ensure `DB_CHARSET=utf8mb4` and `DB_COLLATION=utf8mb4_unicode_ci` in `.env`. |
