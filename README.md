# Droppie Track

Droppie Track is a Laravel application for tracking driving routes. Users select validated Google addresses, and route distances are calculated asynchronously with Google Routes API.

Test application: [https://droppie.asquare.ee/](https://droppie.asquare.ee/)

## Features

- Laravel Sanctum authentication
- Google OAuth sign-in
- Google Places address autocomplete and validation
- Optional Google Address Validation API verification
- Asynchronous route distance calculation
- Reusable normalized address records
- Route filtering, profile management, and PDF reports

## Requirements

- PHP 8.3+
- Composer
- Node.js 22+ and npm
- MariaDB 11.4 (production) or MySQL 8+, utf8mb4
- Google Cloud project with billing enabled

Enable these Google Cloud APIs:

- Routes API
- Places API / Places API (Legacy)
- Address Validation API, if postal validation is enabled

## Installation

```bash
git clone https://github.com/aSquare-developer/droppie-laravel-api.git
cd droppie-laravel-api

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configure the database and Google API credentials in `.env`:

```env
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=droppie_track
DB_USERNAME=root
DB_PASSWORD=

GOOGLE_MAPS_API_KEY=your-google-api-key
GOOGLE_CLIENT_ID=your-google-oauth-client-id
GOOGLE_CLIENT_SECRET=your-google-oauth-client-secret
GOOGLE_REDIRECT_URI=/auth/google/callback
GOOGLE_ROUTES_API_KEY="${GOOGLE_MAPS_API_KEY}"
GOOGLE_PLACES_API_KEY="${GOOGLE_MAPS_API_KEY}"
GOOGLE_ADDRESS_VALIDATION_API_KEY="${GOOGLE_MAPS_API_KEY}"
GOOGLE_ADDRESS_VALIDATION_ENABLED=false
GOOGLE_MAPS_LANGUAGE=en
GOOGLE_PLACES_COUNTRY=FI,EE
```

On MySQL use `DB_CONNECTION=mysql`; both connections read the same `DB_*` settings.

Then run:

```bash
php artisan migrate
composer dev
```

`composer dev` starts the Laravel server, queue listener, logs, and Vite.

## Google OAuth

Create a Web application OAuth client in Google Cloud Console. Add the full callback URL to its authorized redirect URIs, for example:

```text
https://droppie.example.com/auth/google/callback
```

Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in the application environment. The relative `GOOGLE_REDIRECT_URI` uses `APP_URL`, so production must have the correct public HTTPS URL configured.

## Address Validation

Routes accept Google `place_id` values instead of free-form address strings. A valid address must include a postal code, city, country, street, house number, and coordinates.

Addresses are stored in the reusable `addresses` table. The `routes` table stores only `start_address_id` and `end_address_id`.

The address catalog is shared only for `place_id` deduplication. Autocomplete history is stored in `address_usages` and scoped to the authenticated user, so one user cannot receive addresses previously entered by another user.

The profile has a country (ISO code, chosen from a list) and a home city. When the city is saved, its coordinates are looked up once with Places "Find Place" (same Places API key). Suggestions from Google then prefer that country (`region`) and places within 50 km of the city (`location`/`radius`); `GOOGLE_PLACES_COUNTRY` still restricts them. If the city cannot be found, the profile is saved without the city bias.

Autocomplete lists the user's matching history first (marked "Recent") and asks Google Places only when the history has fewer than five matches; Google results already in the history are skipped. If Google is unavailable, the history matches are still returned.

## Trip Reports and Mileage

The vehicle mileage in the profile is the start reading of the next trip report. Generating a report moves it to the report's end reading. To avoid counting trips twice, a new report must start after the latest report of the vehicle; overlapping or earlier periods are rejected. Requesting the same period again downloads the stored report.

Only the latest report can be deleted. Its start reading goes back to the profile mileage (unless the mileage was changed by hand since), so the period can be generated again.

## Queue and Scheduler

Route distance calculation runs through the Laravel queue on the `database` connection (`QUEUE_CONNECTION=database`); no Redis is needed. Temporary Google Routes failures are retried by the queue (3 tries, 15 s and 60 s apart) with one HTTP attempt per try (`GOOGLE_ROUTES_MAX_ATTEMPTS=1`), so a route costs at most three Google requests during an outage.

Locally, `composer dev` runs a queue listener, or run one by hand:

```bash
php artisan queue:work
```

The Laravel scheduler (`routes/console.php`) runs these tasks:

- `queue:work --stop-when-empty --max-time=50` every minute: processes the queued jobs and exits, so no long-running worker or process manager is needed.
- `routes:recover-stuck` every ten minutes: marks routes stuck in `pending` or `processing` for 60 minutes as failed (`--minutes` changes this), so they can be edited or deleted again.
- `sanctum:prune-expired --hours=24` daily at 03:17: deletes API tokens that expired more than a day ago.
- `queue:prune-failed --hours=720` daily at 03:27: deletes failed jobs older than 30 days.

Check them with `php artisan schedule:list`.

### Failure alerts

Set `ADMIN_ALERT_EMAIL` to get an email when background work fails:

- a queued job has used up its retries (for example Google Routes stayed unavailable);
- Google rejects the route request or the Routes API key is missing, which needs the administrator rather than the user.

"No drivable route between the chosen addresses" is not alerted: it is shown on the route itself. At most one email is sent per kind of failure every `ADMIN_ALERT_THROTTLE_MINUTES` (default 15); the next email says how many similar failures happened in between. Emails go through the configured mailer, so with `MAIL_MAILER=log` they are written to the log until real mail is set up. A failing mailer never stops the queue worker.

### Production

No Supervisor, systemd or other process manager is needed. A single cron entry runs the scheduler every minute:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The cron entry must run every minute and call the PHP CLI binary (on some shared hosts plain `php` in cron is not the CLI version, so use its full path). The scheduler then starts `queue:work` and `routes:recover-stuck` itself. Remove any separate cron job that runs `queue:work` directly. Deploys run `php artisan queue:restart`, so a worker still running old code exits after its current job.

## Error Monitoring (Sentry)

Errors, failed background jobs and the scheduler are reported to Sentry (project `droppie`, EU region) through `sentry/sentry-laravel`. Without a DSN the SDK sends nothing, so it stays off locally, in CI and in tests.

Set these in the server's `.env`, then run `php artisan config:cache`:

```env
SENTRY_LARAVEL_DSN=https://...@....ingest.de.sentry.io/...   # Sentry → Project settings → Client Keys (DSN)
SENTRY_TRACES_SAMPLE_RATE=0.1                                 # optional, share of requests traced
```

Check the setup on the server:

```bash
php artisan sentry:test
```

The event shows up in Sentry within a minute.

- **Environment** is `APP_ENV`.
- **Release** is the short git SHA. The deploy writes it to a `REVISION` file next to the code, so there is nothing to set by hand; `SENTRY_RELEASE` overrides it.
- **Background jobs** are reported once they have used up their retries, not on every attempt. A rejected Google request or a missing Routes key is reported right away. "No drivable route" is a normal state of a route and is not an error. The failure emails to `ADMIN_ALERT_EMAIL` keep working alongside Sentry.
- **Scheduler**: `queue:work`, `routes:recover-stuck`, `sanctum:prune-expired` and `queue:prune-failed` send Sentry Cron check-ins. A monitor reports missed runs, so you notice if the server's cron stops. `queue:work` opens an issue only after three missed minutes in a row.
- **Privacy**: no personal data is sent automatically (`send_default_pii` is off), and request bodies are never attached. `App\Support\SentryScrubber` also removes passwords, Sanctum tokens, Google API keys, cookies and SQL values from events, traces and breadcrumbs. The user is identified by id only.

## Roles and Admin Page

Every account has one of three fixed roles: `user` (default), `moderator` or `admin`. Admins can do everything moderators can. The `moderate` gate exists for moderators and admins, but there are no moderator features yet.

Roles can only be changed on the server; registration, Google sign-in and profile updates never touch them:

```bash
php artisan users:set-role mari@example.com admin
php artisan users:set-role mari@example.com user
```

The command refuses to take the admin role from the last admin, so make another user admin first. `GET /api/me` shows the role read-only.

Admins see an **Admin** link in the top menu. `/admin` answers 404 to everyone else, guests included. It shows aggregate figures only, cached for 5 minutes:

- users: total, new, active (by `last_active_at`, updated at most every 5 minutes per user), per role, signed in with Google, and the emails of admins and moderators;
- routes by period and distance status; stored addresses and the 10 most used ones (address and count only);
- PDF reports, cached route distances, queue and failed jobs;
- Google API requests per service today and this month from `api_usage_daily`, the distance cache hit rate, and a warning above 8 000 Routes requests a month (the free Compute Routes Essentials tier is 10 000).

Every real request to Google Routes, Places (Autocomplete, Details, Find Place) and Address Validation is counted per day; distance cache hits are counted as `routes_cache_hit`. A failed count is only logged and never breaks the request.

## Passwords

Web and API registration share one policy (`Password::defaults()` in `AppServiceProvider`): at least 10 characters with letters and numbers. In production new passwords are also checked against Have I Been Pwned (only a hash prefix is sent; if the service is unreachable the check passes), so the server needs outbound HTTPS to `api.pwnedpasswords.com`. Existing passwords keep working.

## API Tokens

Sanctum API tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 30 days). Clients must log in again once a token has expired.

## Tests

```bash
vendor/bin/pest
vendor/bin/pint --test
npm run build
```

Tests run on MySQL/MariaDB, like production; CI uses MariaDB 11.4, the production version. Create an empty test database once (the defaults are in `phpunit.xml`; real environment variables override them):

```sql
CREATE DATABASE droppie_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'droppie'@'127.0.0.1' IDENTIFIED BY 'secret';
GRANT ALL ON droppie_test.* TO 'droppie'@'127.0.0.1';
```

The test suite wipes this database on every run, so never point it at a database with real data.

## Production Deployment

Pushes to `main` run tests, build a production release, and deploy it through `.github/workflows/deploy-production.yml`.

Create a GitHub Environment named `production` and add these secrets:

- `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`
- `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`
- `DEPLOY_PORT` (optional, defaults to `22`)
- `PRODUCTION_URL` (optional health check URL)

Use a dedicated SSH key without a passphrase. Store the complete multiline private key, including its `BEGIN` and `END` lines, in `DEPLOY_SSH_KEY`, and add its public key to the deploy user's `~/.ssh/authorized_keys`. Set `DEPLOY_KNOWN_HOSTS` to the output of `ssh-keyscan -p your-port -H your-server`. The host and port must exactly match `DEPLOY_HOST` and `DEPLOY_PORT`.

Verify the key before deploying with `ssh -i ~/.ssh/droppie_deploy -o IdentitiesOnly=yes -p your-port your-user@your-server true`.

The server must have PHP 8.3+, `tar`, a configured `.env`, and the scheduler cron job described in [Queue and Scheduler](#queue-and-scheduler). The web server must follow symlinks (Apache's default `SymLinksIfOwnerMatch` is enough, since one user owns everything).

### Releases and rollback

`DEPLOY_PATH` is a symlink to the live release. The document root (`DEPLOY_PATH/public`) and the cron command (`cd DEPLOY_PATH && php artisan schedule:run`) never change. Releases and shared files live next to it:

```text
<DEPLOY_PATH>-releases/
├── releases/<UTC time>-<short sha>/   one per deploy, the newest 5 are kept
└── shared/
    ├── .env                           linked into every release
    ├── storage/
    └── well-known/                    public/.well-known (certificate challenges)
```

A deploy (`deploy/release.sh`) runs these steps:

1. Unpack the release and link the shared files.
2. Run `migrate` and `optimize` in the new release while the old one keeps serving.
3. Switch the symlink atomically.
4. Run `queue:restart`.

If any step before the switch fails, the new release is deleted and the old one stays live. Maintenance mode is used only when there are pending migrations, and only while they run. If `/up` fails after the switch, the workflow switches back to the previous release by itself.

To roll back by hand, run the **Deploy production** workflow (Actions → Run workflow) with `action: rollback`. Leave `release` empty for the release before the current one, or name a directory from `releases/`. The same works on the server:

```bash
bash ~/.droppie-deploy/rollback.sh /path/to/DEPLOY_PATH            # previous release
bash ~/.droppie-deploy/rollback.sh /path/to/DEPLOY_PATH <release>  # a specific one
```

Database migrations are not rolled back, so keep migrations backward compatible (add first, remove in a later release).

After a switch, PHP may serve the previous release for up to about two minutes, which is PHP's realpath cache. The old release stays on disk, so this is harmless.

The first deploy converts an existing plain `DEPLOY_PATH` directory into this layout by itself. It copies `.env`, `storage` and `public/.well-known` into `shared/` and keeps the old directory as `releases/00000000000000-legacy`, so it is also the rollback target if that first release fails its health check.

