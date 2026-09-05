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
| 11 | Performance management | ⬜ |
| 12 | Recruitment / ATS | ⬜ |
| 13 | Expenses + loans + assets | ⬜ |
| 14 | Reporting + exports (PDF/Excel/CSV) | ⬜ |
| 15 | Notifications + announcements + email branding | ⬜ |
| 16 | API v1 (Sanctum) + webhooks | ⬜ |
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
