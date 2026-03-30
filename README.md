# survey-analysis-social-mobility

Full-stack application for collecting household survey data and visualizing social mobility across five capitals:
- 👤 ทุนมนุษย์ (Human Capital)
- �� ทุนกายภาพ (Physical Capital)
- 💰 ทุนการเงิน (Financial Capital)
- 🌿 ทุนทรัพยากรธรรมชาติ (Natural Capital)
- 🤝 ทุนทางสังคม (Social Capital)

## Repository Structure

```
survey-analysis-social-mobility/
├── backend/          # Laravel 11 API (PHP)
├── frontend/         # Vue 3 + Vite SPA
├── data/             # Legacy baseline CSV/XLSX
└── docs/             # Survey questionnaire documents
```

## Quick Start

### 1. Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed
php artisan serve
# API → http://localhost:8000
```

Default admin: `admin@example.com` / `password`

### 2. Frontend (Vue 3)

```bash
cd frontend
npm install
npm run dev
# App → http://localhost:5173
```

### 3. Import Legacy Data

1. Login at http://localhost:5173/login
2. Go to Admin → นำเข้าข้อมูล (Import)
3. Upload the CSV/XLSX from the `data/` directory

## Features

- **Bento-style dashboard** (public) showing:
  - จำนวนรหัสบ้าน (distinct house codes imported)
  - จำนวนผู้ตอบ (distinct respondents)
  - Poverty level distribution for each of 5 capitals
  - Mobility comparison (improved / same / decreased)
  - District breakdown
- **Admin area**:
  - Import legacy XLSX/CSV baseline data
  - Create new survey responses via generated form
  - View/manage households, persons, responses
- **Scoring engine**:
  - Normalized capital scores (0–100)
  - Aggregate X in [1.0, 4.0] → poverty levels 1–4
  - Multi-select with configurable weights
  - Q6 physical capital penalty model

## Poverty Level Mapping

| Level | Score Range     | Description |
|-------|-----------------|-------------|
| 1     | 1.00 ≤ X < 1.75 | ยากจนมาก    |
| 2     | 1.75 ≤ X < 2.50 | ยากจน       |
| 3     | 2.50 ≤ X < 3.25 | เปราะบาง    |
| 4     | 3.25 ≤ X ≤ 4.00 | พอเพียง     |

## Tech Stack

- **Backend**: Laravel 11, PHP 8.3, SQLite/MySQL, Sanctum auth, Maatwebsite/Excel
- **Frontend**: Vue 3, Vite, Vue Router 4, Pinia, Axios

## Deployment on Plesk / Apache

### Fixing 404 on Hard Refresh (SPA History Mode)

The frontend uses Vue Router with **HTML5 History Mode** (`createWebHistory()`).  
In this mode, the browser navigates to real URLs such as `/admin/responses/new`, but the
server must always serve `index.html` for those paths because the routing is handled
entirely client-side.

Without extra configuration, refreshing the browser or navigating directly to a
non-root URL returns **HTTP 404** from Apache/Plesk.

#### Why this happens

Apache looks for an actual file or directory matching the URL path.
When no file exists at `/admin/responses/new`, it returns 404 instead of
letting Vue Router handle the route.

#### Fix: `.htaccess` rewrite rule

The file `frontend/public/.htaccess` is included automatically in the Vite build
output (`dist/`) and contains the following rules:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  # Do not rewrite requests for existing files or directories
  RewriteCond %{REQUEST_FILENAME} -f [OR]
  RewriteCond %{REQUEST_FILENAME} -d
  RewriteRule ^ - [L]

  # Do not rewrite API or storage requests (Laravel back-end)
  RewriteRule ^api/ - [L]
  RewriteRule ^storage/ - [L]

  # Route all other requests to the SPA entry point
  RewriteRule ^ index.html [L]
</IfModule>
```

These rules ensure:
- Static assets (JS, CSS, images) are served directly.
- Laravel API routes under `/api/` and uploaded files under `/storage/` are **not**
  rewritten (relevant when the Laravel back-end is deployed under the same domain).
- Every other path falls back to `index.html` so Vue Router can handle it.

#### Deploying on Plesk

1. Build the frontend: `cd frontend && npm run build`
2. Upload the contents of `frontend/dist/` to the Plesk document root (e.g.
   `httpdocs/`).
3. Make sure `mod_rewrite` is enabled in the Plesk domain settings
   (*Apache & nginx Settings → Apache*).
4. Verify that `.htaccess` files are allowed: `AllowOverride All` (or at least
   `AllowOverride FileInfo`) must be set for the document root directory.

If the API (Laravel) is hosted on a **separate subdomain** (e.g.
`api.example.com`), the `RewriteRule ^api/` line is not needed but is harmless.

## Privacy Note

Do not commit real PII data to this repository.
The `data/` directory may contain anonymized sample data only.
