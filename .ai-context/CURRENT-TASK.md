# Current Task

## Scope

- Goal: Pastikan register public benar-benar invite-gated setelah modul super admin invite management tersedia, termasuk pembatasan email/WhatsApp target dan akses form hanya saat invite valid.
- Type: implement
- Mode: balanced
- Allowed modules: controller register public, view register public, service invite registrasi owner, service registrasi owner, test gate invite
- Explicit exclusions: tidak mengubah flow super admin invite management, tidak redesign besar halaman register, tidak menambah migrasi atau modul baru

## Evidence and checkpoint

- Confirmed facts: modul `internal.invites.*` sudah ada untuk create, resend WhatsApp, revoke, dan copy share link register.
- Confirmed facts: halaman `/register` masih menampilkan form penuh walau belum ada invite valid; gate baru benar-benar terjadi saat submit lewat field `invite_code`.
- Confirmed facts: `OwnerRegistrationInviteService::consumeForOwnerRegistration()` baru membatasi email target, belum membatasi `invited_whatsapp_number`.
- Confirmed facts: route `/register` tetap harus public sesuai kontrak alpha, jadi pembatasan perlu hidup di policy gate, bukan dengan menutup route.
- Root cause or hypothesis: flow sekarang belum cukup ketat untuk alpha tertutup karena siapa pun masih bisa masuk ke form penuh dan invite bertarget WhatsApp belum benar-benar membatasi owner yang boleh daftar.
- Next verification: lint file PHP yang diubah, lalu jalankan test invite management + invite gate register.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Requests/RegisterTenantOwnerRequest.php` | validasi email owner | no |
| `app/Http/Controllers/Web/Auth/TenantRegistrationController.php` | gate halaman register public | no |
| `app/Services/OwnerRegistrationInviteService.php` | validasi invite target email/whatsapp | no |
| `app/Services/TenantOwnerRegistrationService.php` | create/reuse owner registration | no |
| `tests/Feature/TenantRegistrationInviteGateTest.php` | regression coverage invite register | no |
| `resources/views/web/auth/register.blade.php` | state form saat invite valid/tidak valid | no |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `TenantRegistrationController`, `OwnerRegistrationInviteService`, `TenantOwnerRegistrationService`, `register.blade.php`, `TenantRegistrationInviteGateTest`
- Contracts to preserve: route `/register` tetap public, format invite code, status invite existing, reuse owner pending yang sudah berjalan, tanpa duplikasi tenant/user
- Verification plan: `php -l` file PHP yang berubah dan `php artisan test --filter=\"TenantRegistrationInviteGateTest|InternalOwnerRegistrationInviteTest\"`
