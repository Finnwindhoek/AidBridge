# AidBridge

B40 financial aid management system — Laravel 12, MySQL, Blade + Bootstrap 5.

Built to the blueprint in `IP_Assignment.md`: five modules, five design patterns,
a REST API, and the security controls the assignment checklist calls for.

---

## Requirements

| Requirement | Version | Notes |
| --- | --- | --- |
| PHP | 8.2 or newer | with `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `zip`, `gd` |
| Composer | 2.x | |
| MySQL / MariaDB | 5.7+ / 10.3+ | the dashboard uses `DATE_FORMAT` and `TIMESTAMPDIFF`, so SQLite will not work for it |

Node and npm are **not** required — the front end uses Bootstrap and Chart.js
from a CDN, so there is no asset build step.

> **XAMPP users:** if `php` is not on your `PATH`, prefix the commands below with
> the full path to the XAMPP binary, e.g. `/opt/lampp/bin/php artisan serve`.

---

## Setup

```bash
# 1. Get the code and its dependencies
git clone https://github.com/Finnwindhoek/AidBridge.git
cd AidBridge
composer install

# 2. Create your environment file and app key
cp .env.example .env
php artisan key:generate

# 3. Create the database (needs an account that can CREATE DATABASE)
sudo mysql -e "CREATE DATABASE IF NOT EXISTS aidbridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'aidbridge'@'localhost' IDENTIFIED BY 'choose-a-password';
               GRANT ALL PRIVILEGES ON aidbridge.* TO 'aidbridge'@'localhost'; FLUSH PRIVILEGES;"

# 4. Put that password into .env as DB_PASSWORD, then confirm the connection
php artisan db:show

# 5. Build the schema and demo data
php artisan migrate:fresh --seed

# 6. Serve
php artisan serve
```

Then open <http://localhost:8000>.

`php artisan key:generate` in step 2 is not optional — `APP_KEY` encrypts every
stored NRIC, and the app will not boot without it.

### Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@aidbridge.test` | `password123` |
| Beneficiary | `siti@example.test` (and 9 others) | `password123` |

### Optional settings

Both are safe to leave blank — the features fail closed rather than breaking the app.

| Variable | Effect if unset |
| --- | --- |
| `PAYMENT_WEBHOOK_SECRET` | `POST /webhooks/payments` rejects every request with 401. Set it to any long random string to exercise the webhook. |
| `AGENCY_VERIFY_ENABLED` | Set `false` to skip the external registry lookup; eligibility assessment still runs and records the check as unavailable. |
| `MAIL_*` | Status-change notifications are queued but not delivered. Failures are logged, never fatal. |

### Tests

```bash
php artisan test
```

The suite runs against in-memory SQLite, so it needs no database setup and can
be run immediately after step 2.

The suite runs against in-memory SQLite and covers RBAC, tenancy isolation, NRIC
encryption, upload rejection, all five patterns, budget integrity and webhook
idempotency.

---

## Architecture

```
[ Blade Views  |  REST API (Sanctum) ]
                  │
       [ Routes + Middleware (auth, role:admin, signed, throttle) ]
                  │
            [ Controllers ]  ← thin: validate, delegate, redirect
                  │
      ┌───────────┴────────────┐
      ▼                        ▼
[ Service Layer ]      [ External Web Services ]
  (design patterns)      AgencyVerificationClient
      │                  Payment gateway webhooks
      ▼
[ Repository Layer / Eloquent Models ]
      │
      ▼
[ MySQL ]
```

Controllers never contain business rules. Services own the domain logic; the
repository owns every query that touches money.

---

## Modules and patterns

| # | Module | Pattern | Where it lives |
| --- | --- | --- | --- |
| 1 | Aid Programme Management | **Factory** | `app/Services/AidProgram/AidProgramFactory.php` + `Types/` |
| 2 | Applications & Documents | **Observer** | `app/Observers/ApplicationObserver.php` |
| 3 | Verification & Eligibility | **Strategy** | `app/Services/Eligibility/Strategies/` |
| 4 | Fund Allocation & Disbursement | **State** + **Repository** | `app/Services/Disbursement/States/`, `app/Repositories/` |
| 5 | Reporting & Monitoring | **Builder** | `app/Services/Reporting/ApplicationReportBuilder.php` |

### 1. Factory — programme types

`AidProgramFactory` maps a type discriminator onto a concrete configuration
object that owns that type's defaults, required documents, and payout formula:

- `CashDisbursementProgram` — base + RM50 per dependent, capped at 5
- `VoucherProgram` — base + RM50 per dependent, rounded down to whole vouchers
- `EmergencyGrantProgram` — base scaled 1.0×–1.5× by eligibility score

Adding a fourth type means adding one class and one registry line.

### 2. Observer — audit trail and notifications

`ApplicationObserver` hooks the Application model's `created`/`updated`/`deleted`
events. Because it is bound to the model rather than a controller, *every* write
path — web, API, seeder, queue, console — produces the same audit record and
fires the same beneficiary notification. Sensitive keys are redacted by
`AuditLogger` before anything is persisted.

### 3. Strategy — eligibility scoring

`EligibilityStrategyInterface` has three implementations:

- `B40IncomeStrategy` — the means test; the only strategy that can disqualify.
  The threshold is raised RM400 per dependent, since income is shared.
- `DisabilitySupportStrategy` — uplift for OKU households, higher when a
  verified certificate is on file.
- `EmergencyReliefStrategy` — priority for disaster-affected households.

The set is assembled in `AppServiceProvider`, not hard-coded in the service, so
new rules are additive (Open/Closed).

### 4. State + Repository — the money

State classes encode the lifecycle so an illegal move is refused by the domain
model itself:

```
Pending ──▶ Approved ──▶ Disbursed ──▶ Reconciled
   └───────────┴────────────┴──────────▶ Failed
```

`DisbursementRepositoryInterface` isolates every ledger query. Budget commitment
is a single conditional `UPDATE` guarded by `budget_remaining >= amount`, so two
concurrent approvals cannot overdraw a programme. All transitions run inside
`DB::transaction()` with `lockForUpdate()`.

### 5. Builder — analytical reports

`ApplicationReportBuilder` assembles filtered queries fluently:

```php
ApplicationReportBuilder::make()
    ->approved()->inState('Selangor')->withMinDependents(4)
    ->decidedBetween('2026-04-01', '2026-06-30')
    ->get();
```

Values are bound parameters; the sort column is checked against an allow-list,
because a column name cannot be bound.

---

## Security controls

| Threat | Control |
| --- | --- |
| SQL injection | Eloquent/query builder throughout; sort columns allow-listed |
| XSS | `{{ }}` escaping in every view; JSON embedded via `@json` |
| CSRF | `@csrf` on all state-changing forms; webhook exempt but HMAC-signed |
| Broken access control | `role:` middleware **and** Policies on every resource |
| Privilege escalation | `role` is never mass-assignable; set by the controller |
| PII exposure | NRIC encrypted via `Crypt`; only last 4 digits ever rendered; excluded from exports |
| Insecure file upload | Form Request `mimes` + `mimetypes`, then content re-sniffed on disk; generated filenames |
| Direct file access | Private disk outside the web root; time-limited signed URLs + policy check |
| Enumeration | UUID/slug route keys — primary IDs never appear in URLs |
| Duplicate payouts | Unique idempotency key on webhook receipts; one live application per programme |
| Budget overdraw | Conditional atomic UPDATE inside a locked transaction |
| Brute force | Rate limiting on login (web + API) and on the webhook endpoint |

---

## REST API

Sanctum bearer tokens; abilities mirror the user's role.

| Method | Endpoint | Notes |
| --- | --- | --- |
| POST | `/api/login` | Returns a 30-day token |
| POST | `/api/logout` | Revokes the current token |
| GET | `/api/me` | Profile with masked NRIC |
| GET | `/api/programmes` | Programmes open for application |
| GET | `/api/applications` | The caller's own applications |
| GET | `/api/applications/{reference}` | Policy-checked detail |
| GET | `/api/reports/metrics` | Dashboard JSON — admin ability required |

### Payment webhook

`POST /webhooks/payments`, signed with `X-AidBridge-Signature`
(HMAC-SHA256 of the raw body using `PAYMENT_WEBHOOK_SECRET`):

```json
{
  "idempotency_key": "evt_abc123",
  "event_type": "payment.completed",
  "reference_code": "AB-20260810-XXXXXXXX",
  "bank_reference": "BNK123456"
}
```

Events: `payment.completed`, `payment.settled`, `payment.failed`.
A retried key returns `200 {"status":"duplicate"}` and changes nothing.

---

## Schema

| Table | Purpose |
| --- | --- |
| `users` | Accounts, `role`, encrypted `nric_encrypted` |
| `aid_programs` | Programmes, type, budget allocated/remaining |
| `applications` | Submissions, eligibility score and breakdown |
| `documents` | Evidence metadata; files live on the private disk |
| `disbursements` | Financial ledger with lifecycle status |
| `webhook_receipts` | Idempotency ledger for gateway callbacks |
| `audit_logs` | Polymorphic, append-only audit trail |
