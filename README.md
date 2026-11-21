# Adendan Web App

Management system for the Adendan charity association: a staff dashboard, a public
website, and a JSON API for the members' mobile app.

## Stack

- PHP 8.2+ (8.3 recommended), Laravel 12, MySQL 8
- Blade + Bootstrap 5 + Vite
- Sanctum (mobile API), Spatie Permission and Activitylog
- Paymob (online payments), EgyptLinx (SMS), Firebase Cloud Messaging

## Architecture

A modular monolith. Each module under `app/Modules` has the same layers, and
dependencies only point inwards:

| Layer | Holds | Knows about |
| --- | --- | --- |
| `Domain` | Enums, value objects (`Money`), rules, contracts (`PaymentGateway`, `SmsSender`) | nothing else |
| `Application` | Use cases (`PayDue`, `RegisterMember`, `ManageNews` ...), report queries | Domain, Eloquent models |
| `Infrastructure` | Paymob, EgyptLinx, Firebase, Excel imports, file storage | Domain contracts |
| `Http` | Thin controllers, form requests, the module's routes | Application |

| Module | Covers |
| --- | --- |
| `Finance` | Dues, office and online payments, donations, safe/bank ledger, expenses, finance reports |
| `Members` | Members, profile-change approvals, member reports, mobile login, public dues lookup |
| `Messaging` | Paid SMS service, reminders, announcements, push notifications |
| `Content` | News, comments, board, committees, website home |
| `Directory` | Tombs, weddings, craftsmen |
| `Access` | Staff accounts, roles, permissions, activity log |
| `Shared` | `Money`, image storage, Excel value parsing, dashboard alerts |

Rules worth knowing:

- Every money movement goes through `Finance\Application\Ledger`, which writes
  `payment_transactions` and moves the safe or bank balance in one transaction.
- Eloquent models stay in `app/Models`: Sanctum, Spatie and the activity log store
  their class names in the database.
- Stored Arabic values (for example `إشتراك`) live in enums, never as strings in code.
- Each module registers its own routes from its service provider (`config/app.php`).

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
