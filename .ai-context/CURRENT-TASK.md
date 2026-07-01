# Current Task

## Scope

- Goal: Update UI `internal/users` agar mengikuti pola halaman user tenant, lalu tampilkan hanya fungsi manage user yang memang sudah ada di backend internal.
- Type: implement
- Mode: balanced
- Allowed modules: `app/Http/Controllers/Internal/ResourcePageController.php`, `resources/views/internal/users/index.blade.php`, test feature internal users yang paling dekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak mengubah flow auth internal, tidak menambah route/action baru untuk activate/deactivate user, tidak redesign modul internal lain

## Evidence and checkpoint

- Confirmed facts: `internal/users` masih memakai `internal.resource-index` generik sehingga tampilannya belum selaras dengan page tenant members/accounts.
- Confirmed facts: action backend internal yang benar-benar tersedia untuk manage user saat ini adalah inspect user, buka tenant detail, resend activation code, dan regenerate activation code.
- Confirmed facts: route resend/regenerate hanya tersedia di `internal.verification.*`, jadi halaman users harus reuse kontrak itu, bukan membuat endpoint baru.
- Confirmed facts: style tenant yang paling dekat untuk user management ada di `resources/views/tenant/members/index.blade.php` dengan `members-*` table shell dan `accounts-action-menu`.
- Confirmed facts: CSS untuk summary card, table shell, action menu, pagination, dan modal detail sudah ada, jadi tidak perlu membuat style system baru.
- Root cause or hypothesis: halaman users internal terlalu generik untuk kebutuhan manage user; solusi paling kecil adalah view khusus users dengan data/controller yang sama, ditambah action pending-verification yang memang sudah didukung service.
- Next verification: review diff, jalankan test feature internal users terfokus, validasi sintaks PHP controller, lalu cek route internal users/verifikasi tetap terbaca.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `routes/internal.php` | `internal.users.index`, `internal.verification.*` | re-read |
| `app/Http/Controllers/Internal/ResourcePageController.php` | `showUsers()`, `buildUserDetail()` | re-read |
| `app/Http/Controllers/Internal/VerificationController.php` | resend/regenerate contract dan redirect | re-read |
| `resources/views/internal/resource-index.blade.php` | view generik lama untuk internal users | re-read |
| `resources/views/tenant/members/index.blade.php` | referensi UI manage user tenant | re-read |
| `resources/views/tenant/accounts/index.blade.php` | referensi pagination dan action menu | re-read |
| `tests/Feature/InternalOwnerRegistrationInviteTest.php` | pola setup auth internal | re-read |
| `tests/Feature/InternalWahaActionTest.php` | pola feature test internal lain | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Http/Controllers/Internal/ResourcePageController.php`, `resources/views/internal/users/index.blade.php`, `tests/Feature/InternalUsersPageTest.php`
- Contracts to preserve: daftar user tetap latest-first, detail tenant tetap lewat route existing, resend/regenerate tetap memakai route verifikasi existing, user verified tidak ditampilkan seolah bisa diregenerate
- Verification plan: review `git diff`, jalankan `php artisan test --filter=InternalUsersPageTest`, `php -l` untuk controller internal resource page, dan `php artisan route:list --name=internal.users`
