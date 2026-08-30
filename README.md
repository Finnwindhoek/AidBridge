# AidBridge

B40 financial aid management system — Laravel 12, MySQL, Blade + Bootstrap 5.

Built to the blueprint in `IP_Assignment.md`: five modules, eight design patterns,
a REST API, and the security controls the assignment checklist calls for.

---

## Requirements

| Requirement | Version | Notes |
| --- | --- | --- |
| PHP | 8.2 or newer | with `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `zip`, `gd` |
| Composer | 2.x | |
| MySQL / MariaDB | 5.7+ / 10.3+ | the dashboard uses `DATE_FORMAT` and `TIMESTAMPDIFF`, so SQLite will not work for it |

Node and npm are **not** required — the front end uses Bootstrap 5, Bootstrap
Icons and Chart.js from a CDN, plus one hand-written stylesheet at
`public/css/aidbridge.css`. There is no asset build step.

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
be run immediately after step 2. It covers RBAC, tenancy isolation, NRIC
encryption, upload rejection, all eight patterns, budget integrity, webhook
idempotency, and a render pass over every screen.

Two things to know:

- Your PHP binary needs the **`pdo_sqlite`** extension. Many distro builds omit
  it; the XAMPP binary has it, so use `/opt/lampp/bin/php artisan test` if
  `php artisan test` reports *could not find driver*.
- The dashboard is excluded from the render test. `DashboardMetricsService` uses
  MySQL's `DATE_FORMAT` and `TIMESTAMPDIFF`, which SQLite does not implement, so
  that one screen is verified against MySQL rather than in the suite.

---

## Architecture

```
[ Blade Views  |  REST API (Sanctum) ]
                  │
       [ Routes + Middleware (auth, role:admin, signed, throttle) ]
                  │
            [ Controllers ]  ← thin: validate, delegate, redirect
                  │
      [ ApplicationWorkflowFacade ]  ← one entry point for the admin case flow
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
| — | Admin case handling (cross-module) | **Facade** | `app/Services/Workflow/ApplicationWorkflowFacade.php` |
| — | Audit correlation (cross-cutting) | **Singleton** | `app/Support/RequestContext.php` |

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

### 6. Facade — closing an aid application

Reviewing and closing a case is not one operation. It spans the Strategy chain,
the external registry, the application's status machine, the Observer's audit and
notification fan-out, and the ledger raising a payout sized by the Factory type.

`ApplicationWorkflowFacade` owns that ordering so controllers do not have to:

```php
$breakdown = $workflow->review($application, $admin);   // assess + move under review
$closure   = $workflow->close($application, $admin, approved: true);

$closure->disbursement;              // raised automatically on approval
$closure->needsManualDisbursement(); // true if the payout could not be raised
```

`EligibilityController` went from three injected services and hand-rolled
sequencing to one collaborator. The subsystems are untouched and still usable on
their own — a facade simplifies access to a subsystem, it does not replace it.

One rule lives here and nowhere else: the decision and the payout are **not** a
single transaction. A recorded decision is the legally meaningful act and must
survive; a payout that cannot be raised (misconfigured amount, exhausted budget)
is an operational problem to retry, not a reason to un-approve a beneficiary who
qualified. The failure is reported on the result object instead of rolled back.

> Note: this is the GoF Facade — an ordinary injected object. It is unrelated to
> Laravel's `Illuminate\Support\Facades` static proxies.

### 7. Singleton — audit correlation

One admin click fans out across classes that know nothing about each other:
`EligibilityService` writes `application.assessed`, `ApplicationObserver` writes
`application.status_changed`, `DisbursementService` writes `disbursement.created`.

`RequestContext` is a textbook singleton — private constructor, private `__clone`,
`__wakeup()` barred, reachable only via `getInstance()` — holding one correlation
ID for the lifetime of the request. Every `AuditLogger` write stamps it, so the
audit trail can be grouped back into the single action that caused it:

```php
AuditLog::correlatedWith($id)->get();   // every row from one admin action
```

The audit report shows the first 8 characters as a `trace` badge, so rows
belonging to one action can be picked out by eye.

Lifetime is managed at both ends that are not a plain PHP-FPM request: the test
suite resets it between tests, and a queue worker resets it between jobs — except
on the `sync` driver, which runs the job inline inside the dispatching request and
must therefore keep that request's trace.

---

## Front end

Server-rendered Blade on Bootstrap 5. No build step, no JavaScript framework —
the only scripts are Bootstrap's bundle and Chart.js on the dashboard.

| Piece | Where |
| --- | --- |
| Palette, component overrides, print styles | `public/css/aidbridge.css` |
| Authenticated shell (navbar, footer, alerts) | `resources/views/layouts/app.blade.php` |
| Sign-in / register shell | `resources/views/layouts/guest.blade.php` |
| Reusable UI components | `resources/views/components/` |

### Shared components

Five anonymous Blade components keep the twelve screens consistent instead of
each one repeating the same markup:

| Component | Purpose |
| --- | --- |
| `<x-page-header>` | Title, subtitle, breadcrumbs and an actions slot |
| `<x-status-badge>` | Renders any status enum via its `label()`, `colour()` and `icon()` |
| `<x-stat-card>` | Dashboard and summary counter tiles |
| `<x-filter-card>` | The GET filter bar above every listing |
| `<x-empty-state>` | Shown in place of an empty table, with an optional action |

`<x-status-badge>` works for application, disbursement and programme statuses
alike because all three enums expose the same three methods.

### Accessibility

- Every badge carries an icon as well as a colour, so status is never signalled
  by colour alone.
- A skip-to-content link, `aria-current` on the active nav item, labelled form
  controls, `scope` on table headers and captions on data tables.
- Visible focus outlines on links, buttons and form fields.

### Notes

- Pagination is switched to Bootstrap markup in `AppServiceProvider`
  (`Paginator::useBootstrapFive()`). Laravel ships Tailwind markup by default,
  which this project does not load.
- The Malaysian state list lives in `config/aidbridge.php` rather than being
  repeated in the three forms that need it.
- `PageRenderTest` renders every screen as the role that owns it, so a broken
  component or an undefined view variable fails the suite rather than the browser.

---

## Help assistant

A beneficiary-facing FAQ widget, offered as a floating panel on every page once
signed in. It answers the questions an aid office actually fields — "where is my
payment?", "what documents do I need?", "why was I rejected?" — from the asker's
own records.

It is **rule-based**: no external API, no API key, no per-message cost, and it
works with the network down. Under the hood it is the Strategy pattern again,
the same shape as the eligibility rules:

```
app/Services/Chatbot/
    ChatIntentInterface.php     one contract: name, scoreFor, respond
    KeywordMatcher.php          shared scoring, injected into each intent
    ChatbotService.php          context: picks the highest-scoring intent
    ChatReply.php               message + follow-up chips + deep link
    Intents/                    eight implementations
```

Each intent scores its own confidence for a question; the service lets the
highest scorer answer, and falls back to an honest "I did not understand" when
nothing clears the threshold. Adding a topic means writing one class and adding
one line to `AppServiceProvider` — no existing intent is touched.

Answers are composed from live data rather than canned text, so they cannot drift
out of step with the system: the document intent asks `AidProgramService` for the
real requirement list, and the eligibility intent reads back the reasons the
Strategy chain actually recorded.

**Boundaries, deliberately:**

- Only beneficiaries see the widget — every answer is built from the asker's own
  case, which an administrator does not have.
- Every query is scoped through `$user->applications()`, so one applicant can
  never be shown another's record however the question is phrased. There is a
  test for exactly this.
- Questions are **not stored**. Free text from an applicant may contain personal
  details, and persisting it would put PII somewhere the audit trail's redaction
  rules do not reach.
- The endpoint is throttled to 30 requests a minute and rejects guests.
- Replies are plain text inserted with `textContent`, never `innerHTML`.

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
