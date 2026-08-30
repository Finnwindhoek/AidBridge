# AidBridge — Project Build Report

Complete record of what was built, verified, and fixed.

**Project:** AidBridge — B40 Financial Aid Management System
**Location:** `/opt/lampp/htdocs/AidBridge`
**Built from:** `~/Desktop/IP_Assignment.md` (Laravel High-Level Architecture Blueprint)
**Date:** 10 August 2026
**Status:** Complete and verified end-to-end on live MySQL

---

## 1. Summary

| Metric | Value |
|---|---|
| PHP classes written | 62 |
| Blade views written | 20 (+1 unused Laravel default) |
| Migrations | 9 |
| Database tables | 16 |
| Routes registered | 55 |
| Tests | 28 passing, 86 assertions |
| Modules delivered | 5 of 5 |
| Design patterns implemented | 8 (Factory, Observer, Strategy, State, Repository, Builder, Facade, Singleton) |
| Bugs found and fixed | 6 |

---

## 2. Environment

### What was already present

| Component | Finding |
|---|---|
| PHP | 8.2.12 — **only** at `/opt/lampp/bin/php`, not on `PATH` |
| PHP extensions | All Laravel requirements present (mbstring, openssl, PDO, tokenizer, xml, ctype, json, fileinfo, curl, zip, bcmath, gd, intl) |
| Composer | **Not installed** |
| Node / npm | **Not installed** |
| MySQL | System MariaDB/MySQL 8.0.45 on port 3306; XAMPP's own MySQL not running |
| Database access | `root` used socket auth — blocked, required sudo |
| Working directory | Completely empty |
| Git | Not a repository |

### Decisions taken from those constraints

- **Composer** installed locally to the scratchpad rather than system-wide (no sudo needed).
- **Bootstrap 5 via CDN** instead of Tailwind/Vite — Node isn't installed, so a build step would have been a blocker.
- **Database** created by the user running two `sudo mysql` commands, since socket auth cannot be bypassed from the CLI.

### Packages installed

| Package | Version | Purpose |
|---|---|---|
| `laravel/framework` | 12.65.0 | Framework |
| `laravel/sanctum` | ^4.3 | REST API token authentication |
| `barryvdh/laravel-dompdf` | ^3.1 | PDF report export |

---

## 3. Database schema

### Domain tables (built for this project)

**`users`** — extended Laravel's default migration
`id`, `name`, `email`, `email_verified_at`, `password`, `role` (enum: admin/beneficiary, indexed), `nric_encrypted` (text, Crypt ciphertext), `phone`, `state`, `is_disabled`, `remember_token`, timestamps

**`aid_programs`**
`id`, `title`, `slug` (unique, route key), `description`, `type` (enum, indexed), `budget_allocated`, `budget_remaining`, `payout_amount`, `income_threshold`, `min_dependents`, `status` (enum, indexed), `opens_at`, `closes_at`, `created_by` → users, timestamps

**`applications`**
`id`, `reference` (UUID, unique, route key), `user_id` → users, `aid_program_id` → aid_programs, `status` (enum, indexed), `household_income`, `dependents_count`, `state`, `is_disaster_victim`, `notes`, `eligibility_score`, `eligibility_breakdown` (JSON), `verified_at`, `agency_verification` (JSON), `submitted_at`, `decided_at`, `decided_by` → users, timestamps
*Constraints:* unique `(user_id, aid_program_id)` — the hard anti-double-dipping guard; composite index `(status, aid_program_id)`

**`documents`**
`id`, `application_id` → applications, `document_type` (enum, indexed), `file_path`, `original_name`, `mime_type`, `size_bytes`, `checksum` (SHA-256), `verified_at`, `verified_by` → users, `rejection_reason`, timestamps

**`disbursements`**
`id`, `application_id` → applications, `reference_code` (unique, route key), `amount`, `status` (enum, indexed), `payment_channel`, `bank_reference`, `failure_reason`, `approved_at`, `approved_by` → users, `disbursed_at`, `reconciled_at`, timestamps

**`webhook_receipts`** — idempotency ledger
`id`, `idempotency_key` (**unique** — the duplicate-payout guard), `source`, `event_type`, `disbursement_id` → disbursements, `payload` (JSON), `processed_at`, timestamps

**`audit_logs`**
`id`, `user_id` → users (nullable for system events), `action` (indexed), `auditable_type` + `auditable_id` (polymorphic, composite index), `ip_address`, `user_agent`, `payload` (JSON, redacted), timestamps

### Framework tables
`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`, `personal_access_tokens`, `migrations`

---

## 4. The five modules

### Module 1 — Aid Programme Management · **Factory Pattern**

| File | Role |
|---|---|
| `app/Services/AidProgram/AidProgramFactory.php` | The factory — registry mapping type → concrete class |
| `app/Services/AidProgram/Types/AidProgramType.php` | Abstract product |
| `app/Services/AidProgram/Types/CashDisbursementProgram.php` | Base + RM50/dependent, capped at 5 |
| `app/Services/AidProgram/Types/VoucherProgram.php` | Base + RM50/dependent, rounded **down** to whole RM50 vouchers |
| `app/Services/AidProgram/Types/EmergencyGrantProgram.php` | Base scaled 1.0×–1.5× by eligibility score |
| `app/Services/AidProgram/AidProgramService.php` | Business logic — create, update, archive, payout delegation |
| `app/Http/Controllers/AidProgramController.php` | CRUD + archive |
| `app/Http/Requests/AidProgramRequest.php` | Validation; `type` is `prohibited` on update |

Each type owns its own defaults, required documents, and payout formula. Adding a fourth programme type means one new class and one registry line — no existing code changes.

**Budget-safety rule:** changing a programme's total allocation shifts `budget_remaining` by the same delta, and refuses to drop below what's already committed to approved applications.

---

### Module 2 — Application & Document Management · **Observer Pattern**

| File | Role |
|---|---|
| `app/Observers/ApplicationObserver.php` | The observer — `created` / `updated` / `deleted` |
| `app/Notifications/ApplicationStatusChanged.php` | Queued mail notification |
| `app/Services/Application/ApplicationService.php` | Create, update, submit, withdraw, review, decide |
| `app/Services/Document/DocumentStorageService.php` | Upload, delete, download, safety checks |
| `app/Services/AuditLogger.php` | Central audit writer with PII redaction |
| `app/Http/Controllers/ApplicationController.php` | Application CRUD + submit/withdraw |
| `app/Http/Controllers/DocumentController.php` | Upload, download, delete, verify |
| `app/Http/Requests/ApplicationRequest.php` | Validation |
| `app/Http/Requests/DocumentUploadRequest.php` | `mimes` + `mimetypes` + 4 MB cap |

Because the observer binds to **model events** rather than a controller, every write path — web, API, seeder, queue, console — produces an identical audit record. There is no way to change an application without it being logged.

**Upload security, three layers deep:**
1. Form Request checks extension (`mimes`) *and* sniffed content type (`mimetypes`)
2. `DocumentStorageService` re-sniffs the real file on disk via `getMimeType()` (magic bytes, not the client header)
3. Stored filename is a generated UUID — the user's filename never touches the filesystem path

**Audit redaction:** `password`, `password_confirmation`, `nric`, `nric_encrypted`, `remember_token`, `token`, `api_token` are recursively blanked at any nesting depth before the payload is persisted.

---

### Module 3 — Verification & Eligibility · **Strategy Pattern**

| File | Role |
|---|---|
| `app/Services/Eligibility/EligibilityStrategyInterface.php` | The contract |
| `app/Services/Eligibility/Strategies/B40IncomeStrategy.php` | Means test — the only strategy that can disqualify |
| `app/Services/Eligibility/Strategies/DisabilitySupportStrategy.php` | OKU household uplift |
| `app/Services/Eligibility/Strategies/EmergencyReliefStrategy.php` | Disaster fast-track |
| `app/Services/Eligibility/EligibilityService.php` | Context class — selects, runs, blends |
| `app/Services/Eligibility/EligibilityResult.php` | Immutable result value object |
| `app/Services/External/AgencyVerificationClient.php` | Outbound REST call (Guzzle via `Http` facade) |
| `app/Http/Controllers/EligibilityController.php` | Review queue, assess, decide |

**Scoring:** each strategy returns a 0–100 score; the service produces a **weighted mean** (not a sum) so the result stays on a 0–100 scale regardless of how many strategies apply. Weights: income 1.0, emergency 0.9, disability 0.6.

**Income rule:** the threshold rises RM400 per dependent, since household income is shared. Score is the distance below that adjusted threshold — zero income scores 100, exactly at threshold scores 0.

**Strategy registration** happens in `AppServiceProvider` via contextual binding, so the rule set is configuration rather than hard-coded logic — the Open/Closed Principle in practice.

**External web service:** hashes the NRIC with SHA-256 before sending — the raw identifier never leaves the system. Degrades safely: a failed or disabled lookup returns "unavailable" rather than blocking assessment. Retries twice with backoff, 8-second timeout.

---

### Module 4 — Fund Allocation & Disbursement · **State + Repository Patterns**

| File | Role |
|---|---|
| `app/Services/Disbursement/States/DisbursementState.php` | Abstract state |
| `.../States/PendingState.php` | → Approved, Failed |
| `.../States/ApprovedState.php` | → Disbursed, Failed |
| `.../States/DisbursedState.php` | → Reconciled, Failed |
| `.../States/ReconciledState.php` | Terminal |
| `.../States/FailedState.php` | Terminal |
| `app/Repositories/DisbursementRepositoryInterface.php` | The contract |
| `app/Repositories/EloquentDisbursementRepository.php` | Concrete implementation |
| `app/Services/Disbursement/DisbursementService.php` | Orchestration |
| `app/Http/Controllers/DisbursementController.php` | Admin lifecycle actions |
| `app/Http/Controllers/PaymentWebhookController.php` | Inbound gateway callbacks |

**Lifecycle:**
```
Pending ──▶ Approved ──▶ Disbursed ──▶ Reconciled
   └────────────┴─────────────┴─────────▶ Failed
```

A Pending disbursement **cannot** jump straight to Disbursed — that is the structural guard against an unauthorised payout. Reconciled and Failed are terminal.

**Financial integrity:**
- Every transition runs inside `DB::transaction()` with `lockForUpdate()` — two admins clicking Approve simultaneously cannot both pass the check
- Budget commitment is a single conditional `UPDATE` guarded by `budget_remaining >= amount`, evaluated by MySQL as part of the write — concurrent approvals cannot overdraw
- A failed payout releases its committed budget, but **only** if it was past Pending (releasing from Pending would credit money never debited)
- Budget release is clamped so it can never exceed the original allocation

**Webhook idempotency:** the receipt row is inserted **first**. A retried delivery hits the unique constraint on `idempotency_key`, returns `200 {"status":"duplicate"}`, and changes nothing. Answering 200 (not an error) stops the gateway retrying forever.

**Webhook authentication:** HMAC-SHA256 of the raw request body, compared with `hash_equals` (timing-attack safe). **Fails closed** — no configured secret means no callback is trusted.

---

### Module 5 — Reporting & Monitoring · **Builder Pattern**

| File | Role |
|---|---|
| `app/Services/Reporting/ApplicationReportBuilder.php` | Fluent query builder |
| `app/Services/Reporting/DashboardMetricsService.php` | Dashboard aggregates |
| `app/Http/Controllers/ReportController.php` | Dashboard, reports, exports, audit trail |
| `resources/views/reports/pdf/applications.blade.php` | PDF template |

The blueprint's example report reads as one chained expression:

```php
ApplicationReportBuilder::make()
    ->approved()
    ->inState('Selangor')
    ->withMinDependents(4)
    ->decidedBetween('2026-04-01', '2026-06-30')
    ->get();
```

**Filter steps:** `status`, `approved`, `inState`, `forProgramme`, `forProgrammeType`, `withMinDependents`, `withIncomeBelow`, `withMinScore`, `decidedBetween`, `submittedBetween`, `fundedOnly`, `sortBy`, `applyFilters`
**Terminals:** `get`, `paginate`, `cursor` (streaming), `summary`, `appliedFilters`, `toQuery`

**Injection safety:** all values are bound parameters. Column names *cannot* be bound, so `sortBy` checks against an allow-list and falls back to `created_at`.

**Metrics produced:** headline counters, approval rate, applications by status, 6-month trend, budget utilisation per programme, distribution by state, disbursement pipeline, and aid distribution velocity (mean days submission → payment).

**Exports:** CSV streamed via `streamDownload` + `cursor()` so a large export never builds in memory; PDF via dompdf, capped at 500 rows. **NRIC is deliberately excluded from both.**

---

### Cross-module — **Facade Pattern**

| File | Role |
|---|---|
| `app/Services/Workflow/ApplicationWorkflowFacade.php` | Single entry point for the admin case flow |
| `app/Services/Workflow/ApplicationClosure.php` | Immutable outcome of a closing sequence |
| `app/Http/Controllers/EligibilityController.php` | Reduced to one injected collaborator |

Closing an aid application spans four subsystems — the Strategy chain scores the applicant, the external registry is consulted, the status machine moves, the Observer fans out audit and notifications, and the ledger raises a payout sized by the Factory type. The controller previously drove that ordering by hand.

```php
$breakdown = $workflow->review($application, $admin);   // assess + move under review
$closure   = $workflow->close($application, $admin, approved: true);
```

**Partial-failure rule (owned here, nowhere else):** the decision and the payout are deliberately *not* one transaction. A recorded decision is the legally meaningful act and must survive; a payout that cannot be raised is an operational problem to retry, not a reason to un-approve a qualifying beneficiary. The failure is returned on `ApplicationClosure` and surfaced to the officer as a warning.

This is the GoF Facade — an ordinary injected object, unrelated to Laravel's `Illuminate\Support\Facades` static proxies. The subsystems remain usable directly.

---

### Beneficiary support — **Help assistant** (Strategy, reused)

| File | Role |
|---|---|
| `app/Services/Chatbot/ChatIntentInterface.php` | The one contract every intent implements |
| `app/Services/Chatbot/ChatbotService.php` | Context: scores the question, picks the winner |
| `app/Services/Chatbot/KeywordMatcher.php` | Shared scoring, composed into each intent |
| `app/Services/Chatbot/Intents/` | Eight intents, plus an explicit fallback |
| `app/Http/Controllers/AssistantController.php` | Throttled JSON endpoint |
| `resources/views/components/assistant-widget.blade.php` | Floating panel |

A rule-based FAQ assistant for beneficiaries — no external API, no key, no per-message cost. This is **not a ninth pattern**: it is a second, independent application of Strategy, which is the point. The same contract shape that solved "which eligibility rules apply?" solves "which intent answers this question?".

**Composition over inheritance:** scoring is shared through an injected `KeywordMatcher` collaborator rather than an abstract base class. An intent *has a* matcher; it is not *a kind of* matcher. The intents stay flat and independent.

**Answers are derived, not canned.** `RequiredDocumentsIntent` asks `AidProgramService` for the real requirement list (Factory-backed), and `EligibilityIntent` reads back the reasons the Strategy chain recorded on the application. Neither can drift out of step with the system's actual behaviour.

**Fallback is deliberate.** `FallbackIntent` scores a hard `0.0` so it never competes; it answers only when nothing clears the confidence threshold. Saying plainly that it did not understand beats guessing at a question about someone's aid money.

**Security boundaries:** widget shown to beneficiaries only; every query scoped through `$user->applications()` (tested — one beneficiary cannot surface another's case); questions never persisted, since free text may carry PII the audit redaction rules would not reach; endpoint throttled at 30/min and closed to guests; replies inserted with `textContent`, never `innerHTML`.

---

### Cross-cutting — **Singleton Pattern**

| File | Role |
|---|---|
| `app/Support/RequestContext.php` | One correlation ID per request |
| `app/Services/AuditLogger.php` | Stamps it onto every audit row |
| `database/migrations/..._add_correlation_id_to_audit_logs_table.php` | `audit_logs.correlation_id` |

One admin click produces audit rows from three classes that know nothing about each other. Threading an ID through every constructor would couple them all to a concern none of them own; a single shared instance lets each write pick the same ID up independently.

**Textbook form:** private constructor, private `__clone`, `__wakeup()` throws, access only via `getInstance()`.

**Lifetime:** a PHP-FPM request gets a fresh process anyway. The two cases that do not are handled explicitly — the test suite resets between tests, and a queue worker resets between jobs, *except* on the `sync` driver, which runs the job inline inside the dispatching request and must keep that request's trace.

**Payoff:** `AuditLog::correlatedWith($id)` returns one admin action end to end; the audit report renders the first 8 characters as a `trace` badge.

---

## 5. Supporting architecture

### Enums (`app/Enums/`)
`UserRole`, `ApplicationStatus`, `DisbursementStatus`, `AidProgramType`, `AidProgramStatus`

Each carries `label()` and most carry `colour()` for Bootstrap badges. `DisbursementStatus::stateClass()` is the single place a status string becomes behaviour.

### Models (`app/Models/`)
`User`, `AidProgram`, `Application`, `Document`, `Disbursement`, `AuditLog`, `WebhookReceipt`

- `User` — `HasApiTokens`, encrypted NRIC mutator/accessor, `masked_nric`, `isAdmin()` / `isBeneficiary()`
- `AidProgram` — `acceptingApplications()` scope, budget helpers, slug route key
- `Application` — auto-UUID on create, status scopes, UUID route key
- `Disbursement` — auto reference code, `state()` returns the State object

### Authorization
- `app/Http/Middleware/EnsureUserHasRole.php` — registered as `role:` alias, supports multiple roles
- `app/Policies/` — `ApplicationPolicy`, `DocumentPolicy`, `AidProgramPolicy`, `DisbursementPolicy`

**Defence in depth:** route middleware (`role:admin`) *and* a Policy check on every resource. Either alone would be a single point of failure.

### Authentication
- `Auth/AuthenticatedSessionController` — login/logout, rate limited by email+IP, session regeneration on login
- `Auth/RegisteredUserController` — registration; **role is assigned by the controller, never mass-assigned**
- `Api/AuthController` — Sanctum tokens with role-mirrored abilities, 30-day expiry, constant-time credential check

---

## 6. Views (`resources/views/`)

| Group | Files |
|---|---|
| Layouts | `layouts/app.blade.php` (authenticated shell + role-aware nav), `layouts/guest.blade.php` |
| Partials | `partials/alerts.blade.php` |
| Auth | `auth/login`, `auth/register` |
| Programmes | `aid_programs/index`, `create`, `show`, `edit` |
| Applications | `applications/index`, `create`, `show`, `edit` |
| Eligibility | `eligibility/queue` |
| Disbursements | `disbursements/index`, `show` |
| Reports | `reports/dashboard`, `reports/applications`, `reports/audit`, `reports/pdf/applications` |

`welcome.blade.php` is Laravel's default and is unused — `/` redirects based on auth state.

**UI notes:** Bootstrap 5.3.3 + Bootstrap Icons via CDN; Chart.js 4.4.1 for 5 dashboard charts; custom palette (navy `#10243f`, teal `#0d7d7d`); disbursement lifecycle rendered as a progress tracker driven by the State object; eligibility breakdown shown as a per-strategy accordion with reasons.

---

## 7. Routes — 55 total

### Web — public
`GET /` · `GET|POST /login` · `GET|POST /register`

### Web — authenticated
`POST /logout` · `GET /dashboard` (role-aware) · `GET /programmes` · `GET /programmes/{slug}`
`GET|POST /applications` · `/applications/create` · `/applications/{ref}` · `/edit` · `PUT` · `DELETE` · `PATCH /submit` · `PATCH /withdraw`
`POST /applications/{ref}/documents` · `DELETE /documents/{id}` · `GET /documents/{id}/download` (signed) · `GET /disbursements/{code}`

### Web — admin only (`role:admin`)
Programmes: `create` · `store` · `edit` · `update` · `destroy` · `archive`
Eligibility: `GET /admin/review-queue` · `POST /assess` · `POST /decide`
Disbursements: `GET /admin/disbursements` · `POST /disbursement` · `PATCH /approve` · `/disburse` · `/reconcile` · `/fail`
Reports: `GET /admin/reports` · `/metrics` · `/export/csv` · `/export/pdf` · `/admin/audit-trail`

### REST API (Sanctum)
`POST /api/login` (throttled 10/min) · `POST /api/logout` · `GET /api/me` · `GET /api/programmes` · `GET /api/applications` · `GET /api/applications/{ref}` · `GET /api/reports/metrics` (`role:admin` + `abilities:admin`)

### Webhook
`POST /webhooks/payments` — CSRF-exempt, HMAC-signed, throttled 60/min

---

## 8. Security controls

| Threat | Control | Where |
|---|---|---|
| SQL injection | Eloquent/query builder throughout; sort columns allow-listed | `ApplicationReportBuilder::sortBy` |
| XSS | `{{ }}` escaping in every view; JSON embedded via `@json` | all views |
| CSRF | `@csrf` on every state-changing form | all forms |
| Broken access control | `role:` middleware **and** Policies | routes + `app/Policies/` |
| Privilege escalation | `role` never in `$fillable`; set by controller | `RegisteredUserController` |
| PII at rest | NRIC encrypted with `Crypt`; `nric_encrypted` hidden and unfillable | `User` model |
| PII in UI | Only last 4 digits ever rendered | `masked_nric` |
| PII in exports | NRIC excluded from CSV and PDF entirely | `ReportController` |
| PII to third parties | Only a SHA-256 hash sent to the registry | `AgencyVerificationClient` |
| Malicious upload | 3-layer validation incl. content sniffing; generated filenames | `DocumentUploadRequest` + `DocumentStorageService` |
| Direct file access | Private disk outside web root, `serve => false` | `config/filesystems.php` |
| Unauthorised download | Signed URL (15 min) **plus** Policy check | `documents.download` |
| Response-header injection | `nosniff` + restrictive CSP on downloads | `DocumentStorageService` |
| ID enumeration | UUID and slug route keys — primary IDs never in URLs | models' `getRouteKeyName()` |
| Duplicate payouts | Unique idempotency key; unique `(user_id, aid_program_id)` | migrations |
| Budget overdraw | Conditional atomic UPDATE inside locked transaction | `EloquentDisbursementRepository` |
| Illegal state change | State pattern refuses invalid transitions | `States/` |
| Timestamp spoofing | Lifecycle columns unfillable, written via `forceFill` at trusted boundaries | services/repository |
| Brute force | Rate limiting on web login, API login, webhook | controllers + routes |
| Session fixation | `session()->regenerate()` on login | `AuthenticatedSessionController` |
| Timing attack | `hash_equals` for signature comparison | `PaymentWebhookController` |
| Audit tampering | Append-only log, sensitive keys redacted | `AuditLogger` |

---

## 9. Seed data

`database/seeders/DatabaseSeeder.php` drives data through the **real services**, so seeding exercises the Observer, Strategy, State and Repository paths rather than writing straight to tables.

- **1 admin** — `admin@aidbridge.test` / `password123`
- **10 beneficiaries** — `siti@example.test` and others, all `password123`, spread across 10 Malaysian states, 2 flagged OKU
- **3 programmes** — one per Factory type: Monthly B40 Food Subsidy (cash, RM250k), Back-to-School Fund (voucher, RM120k), Emergency Flood Relief 2026 (emergency grant, RM400k)
- **10 applications** spanning every lifecycle stage: submitted, under review, rejected, approved, disbursed, reconciled
- Submission dates backdated 5–120 days so the trend chart has real spread
- Documents seeded as metadata rows without real files (the download route correctly 404s for these)

---

## 10. Testing — 28 tests, 86 assertions

### `tests/Feature/AccessControlTest.php` (8)
Guest redirects · beneficiary blocked from all admin routes · admin reaches admin routes · cross-beneficiary application access forbidden · index scoped to owner · registration cannot escalate to admin · NRIC encrypted at rest and masked in output · unsigned document download rejected

### `tests/Feature/AidWorkflowTest.php` (20)
**Factory:** payout formulas for all three types · unknown type rejected
**Strategy:** income disqualification · dependents raise threshold · emergency/disability apply only when relevant
**Observer:** audit entry on every status change · payload redaction
**Module 2:** submission blocked without documents · no double application · disguised PHP upload rejected
**State/Repository:** full lifecycle with budget commitment · **submission and decision timestamps persisted** · **document verification persisted** · failed payout releases budget · budget cannot be overdrawn · retried webhook does not pay twice · bad signature rejected
**Builder:** chained filters produce correct results · unknown sort column ignored

Run with `/opt/lampp/bin/php artisan test`.

---

## 11. Verification performed

Everything below was executed and observed — not assumed.

### Schema and data
- 9 migrations applied cleanly to MySQL; 16 tables created
- Seeder completed; NRIC ciphertext 200 chars, decrypts correctly, masks as `••••••••4338`

### Factory payout formulas — verified against seeded records
| Programme | Input | Expected | Actual |
|---|---|---|---|
| Cash | base 500, 3 deps | 650 | **650** ✓ |
| Cash | base 500, 4 deps | 700 | **700** ✓ |
| Cash | base 500, 2 deps | 600 | **600** ✓ |
| Voucher | base 200, 3 deps | 350 | **350** ✓ |
| Emergency | base 1000, score 83 | 1415 | **1415** ✓ |
| Emergency | base 1000, score 85 | 1425 | **1425** ✓ |

### Financial integrity
- Budget arithmetic reconciles exactly: RM770,000 allocated − RM5,140 committed = RM764,860 remaining
- Pipeline totals sum correctly: Approved 2,025 + Disbursed 2,115 + Reconciled 1,000 = 5,140
- Failed payout released exactly its committed amount
- Overdraft attempt correctly refused: *"Insufficient remaining budget… Required RM 650.00, available RM 1.00"*

### State machine guards
- Reconciled → Approve: **blocked** ("A Reconciled disbursement is final")
- Approved → Reconciled: **blocked** ("cannot move to Reconciled. Allowed: Disbursed, Failed")
- Duplicate disbursement: **blocked**

### Strategy scoring
- Flood-affected applicants scored 83–85
- RM6,200 income against RM5,250 threshold: correctly scored 0 and rejected
- 98 audit log entries generated automatically by the Observer

### HTTP sweep (live server, both roles)
- **15 admin pages → 200** (dashboard, programmes, create, edit, review queue, disbursements, detail, reports, metrics, audit trail, applications, detail, CSV, PDF)
- **4 beneficiary pages → 200**
- **4 RBAC blocks → 403** (reports, disbursements, review queue, audit trail)
- **0 errors** in the log after a clean reseed

### Exports
- CSV: 11 lines (header + 10 records), correct columns
- PDF: valid PDF 1.7, 881,227 bytes
- **NRIC confirmed absent from both** (grepped the CSV, ran `strings` on the PDF)

### Dashboard
- 5 Chart.js instantiations render with live data
- MySQL-specific `TIMESTAMPDIFF` and `DATE_FORMAT` queries confirmed working
- Velocity metric: 8.3 days average, 7 fastest, 9 slowest across 4 settled payments

### REST API
- Token issued; `/api/me` returns masked NRIC
- `/api/programmes` returns 3; `/api/applications` returns caller's own
- No token → **401**; beneficiary token on admin metrics → **403**

### Webhook (live, signed)
- Valid signature → `{"status":"processed"}` **200**
- Retry with same key → `{"status":"duplicate"}` **200**, state unchanged
- Forged signature → `{"message":"Invalid signature."}` **401**
- Lifecycle fields persisted: `disbursed_at` and `bank_reference` both written

### Code quality
- Every PHP file passes `php -l`
- All **81 compiled Blade templates** lint as valid PHP

---

## 12. Bugs found and fixed

### 1. Sanctum was never installed
`php artisan install:api` published the API routes file and printed a success message, but the package was absent from `vendor/`.
**Symptom:** seeder crashed on missing `Laravel\Sanctum\HasApiTokens` trait.
**Fix:** installed `laravel/sanctum` and published its migration (`personal_access_tokens` was also missing).

### 2 & 3. `@json` inside JavaScript comments broke two views
Blade compiles `@json` **even without parentheses**, producing `json_encode(, 15, 512)` — invalid PHP.
**Symptom:** fatal parse error; `aid_programs/create` and `reports/dashboard` both 500'd.
**Fix:** reworded both comments.
**Process lesson:** `artisan view:cache` does *not* catch this — it compiles Blade to PHP without parse-checking the output. Now all 81 compiled templates are linted with `php -l`.

### 4. `LEAST()` was MySQL-only
Used in the budget-release query, which made the code unportable and untestable outside MySQL.
**Fix:** rewritten with `increment()` / `decrement()`, which also binds the amount as a parameter instead of interpolating it.

### 5. `pluck(null, 'period')` flattened the trend chart
`pluck` with a null column returns nulls, so every monthly lookup fell through to zero.
**Symptom:** dashboard trend chart showed all zeros despite 10 applications; PHP warnings in output.
**Fix:** `->get()->keyBy('period')`. Verified: trend now reads `0, 1, 2, 3, 4, 0` totalling all 10.

### 6. Mass-assignment silently discarded every lifecycle timestamp — *most significant*
`update()` respects `$fillable`, and these columns were deliberately excluded from it:

| Model | Columns silently dropped |
|---|---|
| `Disbursement` | `approved_at`, `approved_by`, `disbursed_at`, `reconciled_at`, `bank_reference`, `failure_reason` |
| `Application` | `submitted_at`, `decided_at`, `decided_by` |
| `Document` | `verified_by`, `rejection_reason` |

**Why it hid so well:** Laravel's seed command wraps seeders in `Model::unguard()`, so all seeded data looked perfect. Only a real HTTP request exposed it — caught when the webhook returned success but `bank_reference` came back empty.

**Impact:** the entire disbursement audit trail was being lost, along with who approved what and when. Document verification did nothing at all.

**Fix:** `forceFill()->save()` at the trusted service and repository boundaries — deliberately *not* widening `$fillable`. These fields record who did what when, so keeping them unfillable means no crafted request can backdate a submission or spoof an approver. Three regression tests now pin this down.

### Also corrected during the build
- Laravel 12's base `Controller` ships empty — added `AuthorizesRequests` so `$this->authorize()` works
- `ApplicationPolicy::review()` given an optional parameter so the same ability covers both a single record and the review queue
- Two of my own test expectations were wrong: the voucher rounding case (150, not 200 — rounding down is correct behaviour), and a fake-upload test that couldn't work because `UploadedFile::fake()` derives MIME from the filename; rewritten with a real temp file

---

## 13. How to run

```bash
# Database (one-time, needs sudo)
sudo mysql -e "CREATE DATABASE IF NOT EXISTS aidbridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'aidbridge'@'localhost' IDENTIFIED BY 'aidbridge_secret';
               GRANT ALL PRIVILEGES ON aidbridge.* TO 'aidbridge'@'localhost'; FLUSH PRIVILEGES;"

# Build schema + demo data
/opt/lampp/bin/php artisan migrate:fresh --seed

# Serve
/opt/lampp/bin/php artisan serve      # http://localhost:8000

# Tests
/opt/lampp/bin/php artisan test
```

**Accounts:** `admin@aidbridge.test` / `password123` · `siti@example.test` / `password123`

**Server:** `artisan serve` runs PHP's built-in development server. Host/port come from CLI flags or `SERVER_HOST` / `SERVER_PORT`; document root is hardcoded to `public/`.

---

## 14. Notes and limitations

**Do not serve this through XAMPP Apache as-is.** Apache's `DocumentRoot` is `/opt/lampp/htdocs`, and the project sits inside it, so `http://localhost/AidBridge/.env` would be a plain file download — exposing `APP_KEY`, the database password, and the webhook secret. Laravel only ships an `.htaccess` inside `public/`. Use `artisan serve`, or configure a vhost whose `DocumentRoot` is `.../AidBridge/public`.

**Protect `APP_KEY`.** Every stored NRIC is encrypted with it. Lose it and the ciphertext is unrecoverable. If this goes into version control, keep `.env` and `storage/app/private/` out.

**Deliberately MySQL-specific.** `DashboardMetricsService` uses `DATE_FORMAT` and `TIMESTAMPDIFF`. This matches the assignment's target but means the dashboard will not run on SQLite.

**Simulated integrations.** The agency registry points at a placeholder JSON endpoint; `AgencyVerificationClient` is written so a real integration only changes the response interpretation. The payment gateway is exercised by posting signed webhooks.

**Notifications need mail configured.** `ApplicationStatusChanged` is queued and dispatched on every status change, but requires SMTP settings in `.env` to actually deliver. Failures are logged, never rolled back — a mail outage cannot undo an admin's decision.

**Seeded documents have no files.** Document rows exist as metadata; downloads for them correctly return 404.

**Not under version control.** The directory is not a git repository.
