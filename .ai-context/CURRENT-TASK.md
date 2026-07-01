# Current Task

## Scope

- Goal: Perbaiki UI `internal/invites` agar lebih rapi, lalu tampilkan fungsi yang memang sesuai kontrak invite owner alpha yang sudah ada di backend.
- Type: implement
- Mode: balanced
- Allowed modules: `app/Http/Controllers/Internal/OwnerRegistrationInviteController.php`, `resources/views/internal/invites/index.blade.php`, CSS invite/internal modal yang relevan, test feature invite internal yang paling dekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak menambah backend baru untuk invite member tenant, tidak mengubah lifecycle invite, tidak mengubah flow registrasi public invite-gated

## Evidence and checkpoint

- Confirmed facts: route `internal.invites.*` saat ini khusus untuk `owner_registration_invites`, bukan invite member tenant lintas workspace.
- Confirmed facts: aksi backend yang benar-benar tersedia hanya create invite, kirim ulang via WhatsApp, revoke invite, dan konsumsi invite di halaman register owner.
- Confirmed facts: topbar search di layout internal masih visual-only dan belum tersambung ke query controller, jadi tidak layak dipakai sebagai fitur sungguhan di patch ini.
- Confirmed facts: view invite saat ini sudah fungsional, tetapi tabel terlalu padat karena share button dan revoke form inline dalam sel yang sama.
- Confirmed facts: pattern visual paling dekat untuk dirapikan ada di `members-*`, `accounts-action-menu`, dan modal detail yang baru dipakai di halaman internal users.
- Confirmed facts: user sekarang minta menghapus elemen filler yang ditandai merah, jadi kartu "workflow", badge "fungsi aktif", dan presentasi status yang terasa kurang pas perlu disederhanakan.
- Root cause or hypothesis: halaman invite masih terasa seperti dashboard utilitas generik; solusi terkecil adalah menyusun ulang copy/label ke konteks invite owner alpha, memindahkan detail ke modal, dan menyederhanakan action row ke menu yang sesuai status invite.
- Next verification: review diff, jalankan `php -l` untuk controller dan test, `php artisan route:list --name=internal.invites`, `php artisan view:cache`, lalu coba test feature invite terfokus bila environment mengizinkan.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `routes/internal.php` | `internal.invites.*` | re-read |
| `app/Http/Controllers/Internal/OwnerRegistrationInviteController.php` | `index()`, `store()`, `sendWhatsapp()`, `revoke()` | re-read |
| `resources/views/internal/invites/index.blade.php` | current invite UI and actions | re-read |
| `app/Http/Requests/StoreOwnerRegistrationInviteRequest.php` | create validation contract | re-read |
| `app/Http/Requests/RevokeOwnerRegistrationInviteRequest.php` | revoke validation contract | re-read |
| `resources/views/layouts/internal.blade.php` | topbar/search behavior | re-read |
| `resources/css/app.css` | existing invite, table, modal, members styles | re-read |
| `tests/Feature/InternalOwnerRegistrationInviteTest.php` | action coverage and auth setup | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Http/Controllers/Internal/OwnerRegistrationInviteController.php`, `resources/views/internal/invites/index.blade.php`, `resources/css/app.css`, `tests/Feature/InternalOwnerRegistrationInviteTest.php`
- Contracts to preserve: invite tetap untuk onboarding owner alpha, pending invite tetap bisa `send-whatsapp` dan `revoke`, used/expired/revoked tidak ditampilkan seolah bisa dikirim ulang, share URL tetap mengarah ke register page dengan query `invite`
- Verification plan: review `git diff`, jalankan `php -l` pada file PHP yang berubah, `php artisan route:list --name=internal.invites`, `php artisan view:cache`, dan `php artisan test --filter=InternalOwnerRegistrationInviteTest` bila driver test tersedia
