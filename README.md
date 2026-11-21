# Adendan Web App

Management system for the Adendan charity association: a staff dashboard, a public
website, and a JSON API for the members' mobile app.

## Stack

- PHP 8.3, Laravel, MySQL 8
- Blade + Bootstrap 5 + Vite
- Sanctum (mobile API), Spatie Permission and Activitylog
- Paymob (online payments), EgyptLinx (SMS), Firebase Cloud Messaging

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# set DB_* in .env, then:
php artisan migrate --seed
php artisan storage:link
```

Create the first dashboard user: set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env`, then

```bash
php artisan db:seed --class=AdminSeeder
```

Run it:

```bash
php artisan serve
npm run dev
```

## Environment

| Variable | Used for |
| --- | --- |
| `PAYMOB_HMAC_SECRET` | Verifying Paymob callbacks. **Without it every payment callback is rejected.** |
| `PAYMOB_SK`, `PAYMOB_PK`, `PAYMOB_CARD_INTEGRATION_ID`, `PAYMOB_WALLET_INTEGRATION_ID` | Creating Paymob checkout sessions |
| `OCR_API_KEY` | Shared key the OCR engine sends as `X-Api-Key` to `POST /api/ocr-data` |
| `EGYPTLINX_SMS_*` | SMS provider credentials |
| `FIREBASE_CREDENTIALS`, `FIREBASE_PROJECT_ID` | Push notifications |
| `ADMIN_*` | Local admin created by `AdminSeeder` |

## Tests and quality

Tests run on their own MySQL database, `adendan_test`, built from
`database/schema/mysql-schema.sql`. Create it once, and make sure the `mysql` client is on
your `PATH` (Laragon: `D:\laragon\bin\mysql\<version>\bin`).

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

`tests/TestCase.php` refuses to run against any database other than `adendan_test`, so
the tests can never wipe your local data.

CI (`.github/workflows/ci.yml`) runs Pint, Larastan, the tests and `composer audit` on
every push and pull request.

## Windows note

If `git` or `composer` fail with `unable to get local issuer certificate`, an antivirus
is intercepting HTTPS. Use `git -c http.sslBackend=schannel ...`, and point Composer at a
CA bundle that includes the antivirus root with `COMPOSER_CAFILE`.
