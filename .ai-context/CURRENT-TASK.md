# Current Task

## Scope

- Goal: Perbaiki bug agar invite owner tetap bisa dipakai ulang saat email atau nomor WhatsApp sudah terdaftar pada owner yang masih pending verification.
- Type: debug
- Mode: low
- Allowed modules: request registrasi owner, service registrasi owner, test gate invite
- Explicit exclusions: tidak mengubah halaman admin invite, tidak redesign UI, tidak menambah migrasi atau modul baru

## Evidence and checkpoint

- Confirmed facts: `RegisterTenantOwnerRequest` masih memaksa `owner_email` unique pada `tenant_users`.
- Confirmed facts: `TenantOwnerRegistrationService` masih menolak `owner_whatsapp` yang sudah ada di `tenant_users`, tanpa membedakan user verified vs pending.
- Confirmed facts: skema `tenant_users` memang unique untuk email dan nomor WA, jadi resend registrasi hanya aman bila mereuse owner pending yang sama, bukan membuat baris baru.
- Root cause or hypothesis: invite baru untuk calon owner yang pernah registrasi tetapi belum verifikasi gagal di tahap validasi uniqueness, padahal seharusnya akun pending lama direuse dan activation code digenerate ulang.
- Next verification: lint dua file PHP yang diubah dan jalankan test gate invite khusus untuk kasus reuse owner pending.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Requests/RegisterTenantOwnerRequest.php` | validasi email owner | no |
| `app/Services/TenantOwnerRegistrationService.php` | create/reuse owner registration | no |
| `app/Services/CategoryTemplateService.php` | cek idempotensi default category | no |
| `app/Services/ActivationCodeService.php` | regenerate activation code lama | no |
| `tests/Feature/TenantRegistrationInviteGateTest.php` | regression coverage invite register | no |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `RegisterTenantOwnerRequest`, `TenantOwnerRegistrationService`, `TenantRegistrationInviteGateTest`
- Contracts to preserve: route registrasi owner, format invite code, uniqueness final untuk user verified, tanpa duplikasi tenant/user
- Verification plan: `php -l` file yang berubah dan `php artisan test --filter=TenantRegistrationInviteGateTest`
