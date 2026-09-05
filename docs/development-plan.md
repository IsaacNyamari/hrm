# Development plan

Phased build of the multi-tenant HRM SaaS. Each phase ends with: migrations run
clean, `php artisan test` green, Pint clean, PHPStan level 7 clean, a git
commit, and a short report.

Legend: ✅ done · 🚧 in progress · ⬜ not started

| # | Phase | Status |
|---|-------|--------|
| 1 | Multi-tenant architecture (context, scope, middleware, isolation tests) | ✅ |
| 2 | Auth + users + roles + permissions + invitations | ✅ |
| 3 | Tenant onboarding + plans + subscriptions + billing + M-Pesa abstraction | ✅ (registration UI wizard deferred) |
| 4 | Employee management (full profile, related tables, import/export) | ✅ |
| 5 | Departments + designations + branches + org chart | ✅ |
| 6 | Attendance + shifts + work schedules + device abstraction | ✅ |
| 7 | Leave management (types, policies, workflow, balances, holidays) | ✅ |
| 8 | Payroll engine (structures, components, safe formulas, statutory, runs, payslips) | ✅ |
| 9 | Employee self-service portal | ✅ |
| 10 | Documents + expiry + HR letter templates | ✅ |
| 11 | Performance management | ✅ |
| 12 | Recruitment / ATS | ✅ |
| 13 | Expenses + loans + assets | ✅ |
| 14 | Reporting + exports (PDF/Excel/CSV) | ✅ |
| 15 | Notifications + announcements + email branding | ✅ |
| 16 | API v1 (Sanctum) + webhooks | ✅ |
| 17 | Security hardening + audit log + impersonation | ⬜ |
| 18 | Test coverage pass (feature + isolation matrix) | ⬜ |
| 19 | Performance (indexes, eager loading, caching, chunked jobs) | ⬜ |
| 20 | Production deployment docs + config | ⬜ |

## Cross-cutting rules (every phase)

1. Never trust `tenant_id` / `employee_id` / role / payment status from the client.
2. Tenant-owned model → `BelongsToTenant` trait + `tenant_id` migration column + composite indexes.
3. Form Request for validation, Policy for authorization, Service/Action for non-trivial logic.
4. DB transaction for anything financial or multi-row-critical.
5. Queue expensive work; wrap tenant-touching jobs in `Tenancy::run()`.
6. Feature test + tenant-isolation test for every module.
7. `declare(strict_types=1)`, typed signatures, PSR-12, Laravel conventions.

## Decisions log

- **2026-09-05** Single DB, shared schema, row-level `tenant_id` isolation
  (not database-per-tenant). Rationale: spec requires `tenant_id` columns +
  composite indexes + platform-wide reporting; thousands of small tenants.
- **2026-09-05** Tenant context = request/job-scoped singleton + `Tenancy`
  facade; global scope is a no-op when unbound (platform/console/cross-tenant
  jobs), with routing middleware guaranteeing a bound tenant on tenant routes.
- **2026-09-05** `users.email` globally unique (platform-wide staff identity);
  `employees.email` unique per tenant.
- **2026-09-05** Build on the existing Laravel 13 Livewire starter kit (Fortify,
  Livewire 4, Flux 2, Pest 4). No destructive reset.
- **2026-09-05 (P2)** `spatie/laravel-permission` v8 with `teams = true`,
  `team_foreign_key = tenant_id`. **One** authorization system for both sides:
  tenant roles live under their `tenant_id` team; platform (operator) roles live
  under the reserved team id `0` (`TenantContext::PLATFORM_TEAM_ID`) — avoids a
  null team key while keeping everything in Spatie. `Role` / `Permission` /
  `PlatformPermission` enums are the catalogue only; the DB tables are the
  source of truth. `users.is_platform_admin` is kept as a fast denormalised
  gate. Policies evaluate permissions inside the *record's* tenant scope
  (`AuthorizesWithinTenant`) so they are correct outside the HTTP pipeline too.
  (Superseded the earlier `users.platform_role` column — dropped in migration
  `..._181000`.)
- **2026-09-05 (P2)** Invitations carry the tenant via a 64-char single-use
  token (guest route, no subdomain needed); acceptance creates the user +
  assigns the role in one transaction. Login/Failed/Lockout events recorded to
  `login_activities`.
- **2026-09-05 (P3)** Money stored as integer minor units. `PaymentGateway`
  contract with `MpesaGateway` (Daraja STK push) + `ManualGateway`; billing
  never depends on a concrete provider. Webhook idempotency via
  `payment_transactions (provider, external_id)` unique + `PaymentReconciler`
  in a transaction. Success confirmed only server-side. `EnsureTenantSubscribed`
  middleware gates the app on subscription lapse, allowlisting billing/settings.
  `FeatureGate` is the single source for plan limits/flags. Plans are seeded
  data; `subscriptions:expire` / `subscriptions:send-reminders` scheduled daily.
  Deferred: the multi-step public registration wizard UI (backend pieces —
  plans, trial start, subscribe, charge — are all in place).
- **2026-09-05 (P10)** HR letter templates are rendered by `TemplateRenderer`
  with pure `preg_replace_callback` over a whitelisted `{{ placeholder }}` map —
  no Blade, no `eval`, so template bodies are safe tenant-editable content.
  Unknown placeholders resolve to `''` and are reported as `missing`. Generated
  letters are persisted to `generated_letters`; when `save_to_documents` is set
  the rendered HTML is written to the private `local` disk under
  `tenants/{uuid}/employees/{uuid}/letters/` and linked as an `EmployeeDocument`
  (warning/termination letters flagged `is_confidential`). Document expiry:
  `document_types.notify_before_days` (default `[30,14,7]`) drives
  `documents:check-expiry` (daily 07:00), which notifies HR
  (`employees.documents.view`) + the owning employee once per day-mark, tracked
  in `employee_documents.expiry_reminders_sent`. dompdf stays out (blocked in
  this environment) — letters/payslips are print-ready HTML.
- **2026-09-05 (P11)** Performance = `performance_cycles` → `goals`
  (+ `goal_check_ins`) + `kpis` → `performance_reviews` (+ `review_ratings`).
  A cycle is `draft → active → in_review → closed`; opening it fans out one
  `manager` review per active employee (+ a `self` review where the employee
  has a login), idempotently. `PerformanceService` owns the workflow: goal
  check-ins, KPI recording, review save/submit/acknowledge, and `closeCycle`
  which snapshots a weighted `final_score` (`goal_weight` vs
  `competency_weight`, both stored on the cycle) blending goal/KPI attainment
  with the manager's competency ratings. Competency framework is
  `config/performance.php` data (tenant-overridable via `settings.performance`),
  never a table. New permissions `performance.view` / `.manage` / `.review`;
  self-service goal/KPI management needs only a linked employee record, line
  managers can rate and review their reports without a special permission.
  Sidebar navigation added for every module screen (Livewire `wire:navigate`
  SPA); starter-kit Repository/Documentation links and the boilerplate welcome
  page removed.
- **2026-09-06 (P12)** Recruitment / ATS = `job_positions` (reusable role
  templates) → `vacancies` (openings, with a per-vacancy `pipeline_stages`
  JSON) → `applicants` (tenant talent pool, unique per email, private-disk
  résumés) → `applications` (+ `application_activities` timeline) →
  `interviews` (one row per panelist/round) → `offers`. `RecruitmentService`
  owns apply / moveStage / reject / withdraw / rate / scheduleInterview /
  recordInterviewFeedback / makeOffer / sendOffer / respondToOffer; reaching a
  vacancy's final stage marks the application hired and fills a headcount seat
  (auto-closes the vacancy as `filled`). `ConvertOfferToEmployee` turns an
  accepted offer into an `Employee` via the existing `CreateEmployee` action
  (keeps the plan-limit + employee-number logic). Public per-tenant careers
  board: `GET /careers`, `GET /careers/{slug}`, `POST /careers/{slug}/applications`
  under a guest `['tenant']` group (tenant from the request host, never the
  client; only `open` vacancies exposed; apply throttled 6/min). New perms
  `recruitment.view` / `.manage` / `.interview`; panel interviewers submit
  feedback without a recruiter permission. `RecruitmentException` → 422.
  Livewire `Recruitment\Board` pipeline view + sidebar entry.
- **2026-09-06 (P13)** Expenses = `expenses` (claim header, reference
  `EXP-000001`) → `expense_items` (line items, private-disk receipts) with an
  `expense_approvals` chain (`manager` → `finance`, reused
  `LeaveApprovalStatus`, optional `auto_approve_under_cents`). `ExpenseService`
  recomputes the header total and applies `config('expenses.mileage_rate_cents_per_km')`
  (tenant-overridable) to mileage lines; submit → decide (multi-level) →
  reimburse. Assets = `assets` register (unique `asset_tag` per tenant) +
  `asset_assignments`; `AssetService` guards one active assignment per asset
  and flips asset status on assign / check-in / retire. Loans (P8) gained a
  self-service request path, `GET /loans/{loan}/schedule` amortisation, cancel,
  and a Livewire screen. New perms `expenses.view/.approve/.manage`,
  `assets.view/.manage`; self-service claim/loan needs only a linked employee.
  `ExpenseException` / `AssetException` → 422. Livewire `Finance\Expenses`,
  `Finance\Loans`, `Finance\Assets` + a "Finance" sidebar group.
- **2026-09-06 (P14)** Reporting = no new tables. `App\Reports\Report`
  interface + `BaseReport`; seven builders (headcount, attendance-summary,
  leave-ledger, payroll-summary, payroll-register, expense-claims,
  recruitment-funnel), each declaring its own domain permissions. Aggregation
  is done in PHP over tenant-scoped Eloquent queries (no raw GROUP BY, so
  MySQL/sqlite parity and no enum-cast collisions); Collection `where()` on a
  cast column must compare against the enum instance, never its string.
  `ReportRegistry` gates each report on `reports.view` + its permissions in the
  caller's team scope; `ReportService` sanitises filters to the declared keys.
  `ReportExport` (maatwebsite/excel `FromArray`) drives `?format=xlsx|csv`;
  `reports.print` Blade is the print-to-PDF path (dompdf still absent).
  `DashboardMetrics` returns permission-filtered headline cards, shown by
  Livewire `Analytics\Overview` on the rebuilt `dashboard` blade. Livewire
  `Reports\Index` + `reports` sidebar entry. Unknown report key → 404,
  forbidden → 403.
- **2026-09-06 (P15)** Notification centre = Laravel `notifications` table
  surfaced via `NotificationController` (feed / unread-count / read / read-all
  / delete) + Livewire `Notifications\Center`. `notification_preferences`
  (per user × `NotificationCategory`) — `RespectsPreferences` trait gives every
  in-app notification a `via()` that keeps `database` always and toggles `mail`
  per `NotificationChannels::forUser`; 14 notification classes migrated to it
  (billing/leave/expenses/performance/recruitment/documents/announcements/
  hr-requests), only `TenantInvitationNotification` keeps its own `via()`.
  E-mail branding = `tenant_branding` (logo on `public` disk, colours, from/
  reply-to, footer); `ApplyTenantMailBranding` listens on `MessageSending` and
  rewrites From / Reply-To for the bound tenant; published `vendor/mail/html/
  {header,footer}` read a `$tenantBrand` shared by a `View::composer`.
  `BrandingController` + Livewire `Settings\BrandingForm` (`settings.manage`).
  Announcements gained `is_pinned` / `acknowledgement_required` +
  `announcement_reads.acknowledged_at`. Sidebar: "Notifications".
- **2026-09-06 (P16)** `laravel/sanctum` v4 added (`install:api` published the
  config + `personal_access_tokens` migration; `HasApiTokens` on `User`).
  Read-mostly **`/api/v1`** on `routes/api.php`: `auth:sanctum` →
  `ResolveApiTenant` (tenant from the token owner, never the client) →
  `subscription` → `throttle:api` (120/min per token, registered in
  AppServiceProvider). Per-route token abilities via Sanctum's `abilities`
  middleware (aliased in bootstrap/app.php). Endpoints: `me`, `employees`
  index/show, `departments`, `leave-requests` index + write, `payslips`
  index/show — all through `App\Http\Resources\Api\V1\*Resource`. Tokens
  managed from the web app (`ApiTokenController`, session auth) with a
  whitelisted ability set. **Outbound webhooks**: `webhook_endpoints`
  (`secret` encrypted, `events` json, auto-disable after 10 consecutive
  failures) + `webhook_deliveries`. `WebhookService::dispatch(WebhookEvent,
  payload)` fans out to subscribed active endpoints, one queued
  `DeliverWebhook` job each — POSTs signed JSON
  (`X-Webhook-Signature: sha256=hmac(timestamp.body, secret)`), retries with
  backoff, records response. `DispatchWebhooks` subscriber maps
  EmployeeCreated / LeaveApproved / LeaveRejected / ExpenseDecided /
  ApplicationReceived. `WebhookEndpointController` (CRUD + ping + deliveries,
  `webhooks.manage`). Livewire `Developers\Index` + "Developers" sidebar.
  New perms `webhooks.manage`, `api.tokens.manage`.
