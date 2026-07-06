# Current Task

## Session continuity

- Ledger schema: 1
- Task ID: not assigned
- Last snapshot: not captured
- Last revalidated: not validated
- Baseline commit: unavailable
- Working tree at validation: unavailable
- Validation result: required before reusing a previous-session ledger

## Scope

- Goal: Implement forgot-password via WhatsApp for tenant login with neutral response, one-time hashed reset links, rate limit, audit log, and WAHA delivery
- Type: implement
- Mode: deep
- Allowed modules: `routes/web.php`, `app/Http/Controllers/Web/Auth/*`, `app/Models/*`, `app/Enums/*`, `app/Services/*`, `app/Support/PhoneNumberNormalizer.php`, `config/platform.php`, auth-related Blade/CSS, password-reset migration, focused feature tests, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: do not change tenant login credential semantics (tetap email + password), do not redesign public auth shell, do not alter WAHA inbound webhook flow, do not touch platform-admin or tenant dashboard behavior outside the reset-password entrypoints

## Mode budget

- Primary modules: 0
- Implementation files read: 22
- Tests read: 1
- Planned implementation writes: 12
- Actual implementation writes: 12
- Actual test writes: 1
- Budget state: within initial budget
- Expansion approval: not required

## Evidence and checkpoint

- Reproduction/evidence: login Blade already exposes a disabled `Lupa password?` affordance; repo has no tenant password-reset routes, controllers, or migration yet; WAHA outbound delivery already exists via `ActiveBotTargetService` + `WahaClient::sendText()`.
- Confirmed facts: tenant login authenticates against `tenant_users` by email/password and only checks `user_status` after `Auth::attempt()` succeeds; WhatsApp identity is stored on `tenant_users.whatsapp_number_normalized`; WAHA access rules already define the active eligibility boundary as tenant active + service active + user active + verified; repo has no existing password reset token table or audit trail for this flow.
- Root cause or hypothesis: the missing flow is best implemented as a dedicated tenant password-reset module rather than forcing Laravel's default broker, because this product must send reset links through WAHA, keep responses neutral, persist hashed one-time tokens, and audit request/send/use outcomes per nomor/IP.
- Next verification: implement storage + service + controllers + views, then verify request neutrality, WAHA send payload, one-time token consumption, expiry, and rate-limit behavior through focused feature tests plus PHP lint.

## Read ledger

| Path | Purpose / symbols | Read state |
|---|---|---|
| `routes/web.php` | existing tenant guest/auth route pattern | re-read |
| `app/Http/Controllers/Web/Auth/TenantSessionController.php` | login flow and flash style | re-read |
| `app/Http/Controllers/Web/Auth/TenantRegistrationController.php` | public auth page/view conventions | re-read |
| `app/Models/TenantUser.php` | password cast and tenant user auth model | re-read |
| `app/Models/Tenant.php` | tenant status/service status relation | re-read |
| `app/Models/AuditLog.php` | existing audit payload conventions | re-read |
| `app/Enums/UserStatus.php` | active user rule | re-read |
| `app/Enums/VerificationStatus.php` | verified user rule | re-read |
| `app/Enums/TenantStatus.php` | active tenant rule | re-read |
| `app/Enums/ServiceStatus.php` | active service rule | re-read |
| `app/Enums/AuditActorSource.php` | system actor option for audit semantics | re-read |
| `app/Services/ActiveBotTargetService.php` | active bot resolution for outbound WA | re-read |
| `app/Services/ActivationCodeDeliveryService.php` | outbound WA delivery pattern | re-read |
| `app/Services/OwnerRegistrationInviteWhatsappService.php` | WA link message pattern | re-read |
| `app/Services/TenantVerificationCodeService.php` | status checks and platform audit style | re-read |
| `app/Services/OwnerRegistrationInviteService.php` | one-time code lifecycle and snapshot/audit style | re-read |
| `app/Services/Waha/WahaAccessGateService.php` | canonical tenant/service/user eligibility boundary | re-read |
| `app/Services/Waha/WahaClient.php` | WAHA send API contract | re-read |
| `app/Support/PhoneNumberNormalizer.php` | normalized Indonesia WhatsApp input rule | re-read |
| `config/platform.php` | project timeouts/support config home | re-read |
| `config/auth.php` | current auth broker defaults and expiry baseline | re-read |
| `database/migrations/2026_06_19_220100_create_domain_tables.php` | tenant/user/audit schema conventions | re-read |
| `resources/views/web/auth/login.blade.php` | auth-shell markup and forgot-password entry point | re-read |
| `resources/css/app.css` | login card styles to extend | re-read |
| `resources/js/app.js` | auth page JS bootstrapping | re-read |
| `tests/Feature/InternalOwnerRegistrationInviteTest.php` | WAHA outbound feature-test style | re-read |

Read-state meaning:

- `current`: saved fingerprint matches the current file.
- `stale`: file differs from the last verified fingerprint.
- `re-read`: relevant content was refreshed in this session; snapshot before handoff.
- `session-unverified`: inherited without a usable fingerprint.
- `missing`: path no longer exists.
- `unknown`: freshness cannot be established safely.

## Budget expansion log

| Trigger | Additional context/change | Reason | Recommendation | User decision |
|---|---|---|---|---|

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, new password-reset migration/model/enum/services/controllers/tests, `routes/web.php`, `config/platform.php`, `resources/views/web/auth/login.blade.php`, new auth reset Blade views, `resources/css/app.css`
- Contracts to preserve: login tetap memakai email/password; response request reset tetap netral walau nomor tidak eligible; token tidak pernah disimpan plaintext; hanya satu link aktif per user; auth page visual tetap mengikuti shell existing
- Verification plan: focused feature tests for request/send/use/expiry/rate-limit, `php -l` pada file PHP yang diubah, dan review `git diff` terhadap entrypoint login + auth routes

## Completion

- Result: forgot-password via WhatsApp landed for tenant login with neutral request response, hashed one-time reset links, 20-minute expiry, rate limiting per nomor/IP, WAHA delivery, and auditable token/request lifecycle persistence.
- Tests/checks: `php -l` passed for the new PHP files and updated test file; `php artisan route:list --name=tenant.password` registered all 4 public routes; `php artisan view:cache` compiled new/updated Blade successfully; focused runtime verification passed against an isolated temporary MySQL database with `vendor/bin/phpunit tests/Feature/TenantPasswordResetTest.php` -> `OK (7 tests, 86 assertions)`.
- Final budget result: deep-mode scope remained inside auth + WAHA outbound + new persistence module; no unrelated tenant or internal feature behavior was changed.
- Remaining risk: the repo working tree still contains unrelated auth-page edits that predated this finish line, so any follow-up on the login screen should review those together with the new forgot-password entrypoint before shipping.
