# AfyaWatch

A weekly notifiable-disease surveillance dashboard for the 26 regions of Tanzania Mainland. Managers see the whole country; clinicians see only their own region. It shows weekly cases, incidence per 100,000, a control chart, a regional tile map, immunization coverage and a vaccination campaign's social media reach, and it emails the right people when a disease crosses its alert threshold.

All case and campaign figures are **synthetic**. Population denominators come from the 2022 Population and Housing Census (National Bureau of Statistics). See [database/data/README.md](database/data/README.md) for every source and its terms.

## Stack

| Layer | Choice | Why |
| --- | --- | --- |
| Back end | Laravel 12 (PHP 8.2) | Session login, validation, scheduler and mail in one small framework |
| Database | SQLite (MySQL also works) | No database server to run for a small deployment |
| Front end | Vue 3 + Chart.js 4, built with Vite | Interactive filters and charts; public pages ship no JavaScript at all |
| Styling | Plain CSS3 with custom properties | No framework to download; about 3 KB gzipped |

## Run it locally

Requirements: PHP 8.2 with `pdo_sqlite`, Composer, Node 20 or newer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite            # Windows: type nul > database\database.sqlite
php artisan migrate --seed                # about 30 seconds: builds 52 weeks of synthetic data
npm install
npm run build
php artisan serve
```

Open http://localhost:8000 and sign in with one of the demo accounts (password `password` locally; set `DEMO_USER_PASSWORD` in production):

| Account | Role | Sees |
| --- | --- | --- |
| `manager@afyawatch.test` | Manager | All 26 regions, campaign panel, exports |
| `kigoma.clinician@afyawatch.test` | Clinician | Kigoma region only |
| `kagera.clinician@afyawatch.test` | Clinician | Kagera region only |

Create real accounts with `php artisan afyawatch:create-user someone@example.org --role=clinician --region=Kigoma`.

## Alerts

```bash
php artisan alerts:evaluate               # check the latest week and email whoever needs to know
php artisan alerts:evaluate --week=2026-W46 --no-email
php artisan alerts:digest                 # daily summary for users who chose the digest
```

The scheduler runs `alerts:evaluate` every Monday at 07:00 and `alerts:digest` daily at 07:30 (East Africa Time); see [routes/console.php](routes/console.php). Locally, emails are written to `storage/logs/laravel.log`.

Alert rules (in [database/seeders/ConditionSeeder.php](database/seeders/ConditionSeeder.php)):

| Condition | ICD-10 | Rule | Severity |
| --- | --- | --- | --- |
| Cholera | A00 | 1 or more cases in a region in a week | Critical |
| Measles | B05 | 5 or more cases in a region in a week | Warning |
| Bacterial meningitis | G00 | 3 or more cases in a region in a week | Warning |
| Malaria, typhoid, dengue | B54, A01.0, A97 | Above the mean + 3 SD of the previous 8 weeks (SD at least √mean), with a minimum count | Warning |

Controls against alert fatigue are in [app/Services/AlertNotifier.php](app/Services/AlertNotifier.php): one alert per condition, region and week; routing by role and region; a personal setting (immediate, daily digest, or off); and a 28-day cooldown that only re-sends when an alert turns critical or the count doubles.

## Tests

```bash
php artisan test
```

24 tests cover role scoping, login throttling, CSV suppression of small numbers, the FHIR output, the alert rules and cooldown, the digest, the security headers, HTTPS redirect, robots.txt and the sitemap.

## Project layout

```
app/
  Console/Commands/      alerts:evaluate, alerts:digest, afyawatch:create-user
  Http/Controllers/      pages, login, dashboard shell, account
  Http/Controllers/Api/  dashboard JSON, CSV export, alert acknowledgement, FHIR MeasureReport
  Http/Middleware/       ForceHttps, SecurityHeaders (HSTS, CSP), NoIndex
  Services/              SurveillanceStats (all figures), AlertEvaluator, AlertNotifier
  Support/               DashboardFilters (role scoping), Week (ISO weeks), SmallNumbers
database/
  data/                  census CSV, campaign export CSV, data sources README
  seeders/               reference data and the synthetic surveillance generator
resources/
  css/app.css            all styles
  js/dashboard/          Vue app: App.vue, components/, Chart.js setup
  views/                 Blade pages, emails
deploy/                  Nginx site, cron line, update script
tests/                   feature and unit tests
```

## Deployment (small VM)

1. Ubuntu 24.04 VM with Nginx, PHP 8.2-FPM (`php8.2-sqlite3 php8.2-mbstring php8.2-xml php8.2-curl`), Composer and Node.
2. Clone to `/var/www/afyawatch`, then follow "Run it locally" with `APP_ENV=production`, `APP_DEBUG=false`, `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, `APP_URL=https://your-domain` and real SMTP settings in `.env`.
3. Copy [deploy/nginx.conf](deploy/nginx.conf) to `/etc/nginx/sites-available/`, enable it, and run `certbot --nginx -d your-domain`.
4. Add the line in [deploy/crontab.txt](deploy/crontab.txt) to the web user's crontab.
5. For later updates, run [deploy/deploy.sh](deploy/deploy.sh).

Set SPF, DKIM and DMARC records for the sending domain so alert emails are not marked as spam.

## Security and privacy

- Aggregate counts only; no patient-level records exist anywhere.
- Counts from 1 to 4 are flagged on screen and exported as `<5`; case fatality is hidden below 20 cases.
- HTTPS only (redirect + HSTS), Content Security Policy with no inline scripts, `X-Frame-Options: DENY`, `nosniff`.
- 30-minute sessions that end when the browser closes, no "remember me", login limited to 5 attempts a minute.
- Login, logout, exports, FHIR requests and alert acknowledgements are written to the `audit_logs` table.
- `robots.txt`, `X-Robots-Tag` and `noindex` keep the login page, dashboard and API out of search engines. The public summary stays unindexed while `AFYAWATCH_SYNTHETIC_DATA=true`.
