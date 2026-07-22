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
- Node.js and npm
- PostgreSQL
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

Configure PostgreSQL and Google API credentials in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
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

## Queue

Route distance calculation runs through the Laravel queue:

```bash
php artisan queue:work
```

Redis users can run Laravel Horizon:

```bash
php artisan horizon
```

## Tests

```bash
vendor/bin/pest
vendor/bin/pint --test
npm run build
```

## Production Deployment

Pushes to `main` run tests, build a production release, and deploy it through `.github/workflows/deploy-production.yml`.

Create a GitHub Environment named `production` and add these secrets:

- `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`
- `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`
- `DEPLOY_PORT` (optional, defaults to `22`)
- `PRODUCTION_URL` (optional health check URL)

Use a dedicated SSH key without a passphrase. Store the complete multiline private key, including its `BEGIN` and `END` lines, in `DEPLOY_SSH_KEY`, and add its public key to the deploy user's `~/.ssh/authorized_keys`. Set `DEPLOY_KNOWN_HOSTS` to the output of `ssh-keyscan -p your-port -H your-server`. The host and port must exactly match `DEPLOY_HOST` and `DEPLOY_PORT`.

Verify the key before deploying with `ssh -i ~/.ssh/droppie_deploy -o IdentitiesOnly=yes -p your-port your-user@your-server true`.

The server must have PHP 8.3+, `rsync`, a configured `.env`, and writable `storage` and `bootstrap/cache` directories. Background workers and scheduled tasks are managed separately on the server.
